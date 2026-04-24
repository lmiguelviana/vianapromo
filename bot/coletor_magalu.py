"""
coletor_magalu.py — Busca ofertas FITNESS no Magazine Luiza.

Fluxo:
  1. Busca por palavra-chave via página de resultados do Magalu
  2. Extrai dados de produto do __NEXT_DATA__ (Next.js) embutido no HTML
  3. Filtra por desconto mínimo, preço máximo e blacklist
  4. Gera link de afiliado com smttag configurado
  5. Deduplica por produto_id_externo (48h) e salva na tabela `ofertas`
"""
import sqlite3
import requests
import time
import re
import json
import sys
import os
import socket
from urllib.parse import quote

socket.setdefaulttimeout(20)

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('MAGALU')

MAGALU_SEARCH_URL = 'https://www.magazineluiza.com.br/busca/{query}/'

HEADERS = {
    'User-Agent': (
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        'AppleWebKit/537.36 (KHTML, like Gecko) '
        'Chrome/124.0.0.0 Safari/537.36'
    ),
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language': 'pt-BR,pt;q=0.9,en;q=0.8',
    'Accept-Encoding': 'gzip, deflate, br',
    'Cache-Control': 'no-cache',
}

KEYWORDS_MAGALU = [
    # Suplementos
    'whey protein',
    'creatina',
    'pre treino',
    'bcaa',
    'colageno hidrolisado',
    'vitamina d',
    'omega 3',
    'termogenico',
    'multivitaminico',
    'proteina isolada',
    # Academia
    'haltere',
    'anilha',
    'kettlebell',
    'faixa elastica',
    'luva musculacao',
    'corda pular',
    'tapete yoga',
    'roda abdominal',
    # Roupas
    'legging fitness',
    'shorts academia',
    'top academia',
    'camiseta dry fit',
    'conjunto academia',
    'sutia esportivo',
    # Calçados
    'tenis academia',
    'tenis corrida',
    # Acessórios
    'coqueteleira',
    'garrafa termica esportiva',
    'balanca bioimpedancia',
]


def _db_conn() -> sqlite3.Connection:
    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path, timeout=10)
    conn.execute('PRAGMA busy_timeout=10000')
    conn.execute('PRAGMA journal_mode=WAL')
    return conn


def _ja_coletado(produto_id: str, conn: sqlite3.Connection) -> bool:
    """Retorna True se o produto foi coletado nas últimas 48h."""
    row = conn.execute(
        "SELECT 1 FROM ofertas WHERE produto_id_externo = ? "
        "AND coletado_em > datetime('now', '-48 hours', 'localtime')",
        (produto_id,)
    ).fetchone()
    return row is not None


def _na_blacklist(produto_id: str, conn: sqlite3.Connection) -> bool:
    row = conn.execute(
        "SELECT 1 FROM blacklist WHERE produto_id_externo = ?",
        (produto_id,)
    ).fetchone()
    return row is not None


def _gerar_link(url_produto: str, smttag: str) -> str:
    """Adiciona o parâmetro de afiliado na URL do Magalu."""
    if not smttag:
        return url_produto
    sep = '&' if '?' in url_produto else '?'
    return f"{url_produto}{sep}smttag={smttag}&utm_source=parceiro&utm_medium=afiliado"


def _extrair_produtos_next_data(html: str) -> list[dict]:
    """Extrai lista de produtos do __NEXT_DATA__ embutido pelo Next.js."""
    match = re.search(
        r'<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.*?)</script>',
        html, re.DOTALL
    )
    if not match:
        log.debug('  __NEXT_DATA__ não encontrado na página')
        return []

    try:
        data = json.loads(match.group(1))
    except json.JSONDecodeError as e:
        log.debug(f'  Erro ao decodificar __NEXT_DATA__: {e}')
        return []

    # Tenta múltiplos caminhos na estrutura do Next.js (Magalu pode variar)
    page_props = data.get('props', {}).get('pageProps', {})

    # Caminho 1: pageProps.data.products
    produtos = page_props.get('data', {}).get('products', [])

    # Caminho 2: pageProps.search.products
    if not produtos:
        produtos = page_props.get('search', {}).get('products', [])

    # Caminho 3: pageProps.products
    if not produtos:
        produtos = page_props.get('products', [])

    # Caminho 4: pageProps.initialState.search.products
    if not produtos:
        produtos = (
            page_props.get('initialState', {})
                      .get('search', {})
                      .get('products', [])
        )

    return produtos if isinstance(produtos, list) else []


def buscar_keyword(keyword: str, smttag: str, desconto_min: int, preco_max: float,
                   limite: int = 20) -> list[dict]:
    """Busca produtos no Magalu por palavra-chave e retorna lista de ofertas."""
    url = MAGALU_SEARCH_URL.format(query=quote(keyword))
    resultados = []

    for tentativa in range(3):
        try:
            r = requests.get(url, headers=HEADERS, timeout=15)

            if r.status_code == 429:
                espera = 60 * (tentativa + 1)
                log.warning(f'  429 em "{keyword}" — aguardando {espera}s (tentativa {tentativa+1}/3)...')
                time.sleep(espera)
                continue

            if r.status_code == 404:
                log.debug(f'  Nenhum resultado para "{keyword}"')
                return []

            r.raise_for_status()
            break

        except requests.RequestException as e:
            log.warning(f'  Erro de rede em "{keyword}": {e}')
            return []
    else:
        log.warning(f'  Desistindo de "{keyword}" após 3 tentativas.')
        return []

    produtos = _extrair_produtos_next_data(r.text)

    if not produtos:
        log.debug(f'  Nenhum produto extraído para "{keyword}"')
        return []

    for p in produtos[:limite]:
        try:
            preco_por = float(p.get('price', 0) or p.get('priceBase', 0) or 0)
            preco_de  = float(
                p.get('originalPrice', 0) or
                p.get('priceOriginal', 0) or
                p.get('listPrice', 0) or
                preco_por
            )
            if preco_por <= 0:
                continue

            if preco_por > preco_max:
                continue

            desconto = int(((preco_de - preco_por) / preco_de) * 100) if preco_de > preco_por else 0
            if desconto < desconto_min:
                continue

            sku  = str(p.get('sku', p.get('id', p.get('productId', ''))))
            if not sku:
                continue

            titulo = str(p.get('title', p.get('name', p.get('description', ''))))
            if not titulo:
                continue

            # URL do produto — pode vir como path ou URL completa
            url_path = str(p.get('url', p.get('path', p.get('link', ''))))
            if url_path.startswith('http'):
                url_produto = url_path.rstrip('/')
            elif url_path:
                url_produto = f"https://www.magazineluiza.com.br{url_path}".rstrip('/')
            else:
                url_produto = f"https://www.magazineluiza.com.br/produto/{sku}/p/{sku}/"

            imagem = str(
                p.get('thumbnail', '') or
                p.get('image', '') or
                p.get('img', '')
            )

            # Preferir produtos vendidos pelo próprio Magalu (comissão 17-19%)
            seller = p.get('seller', {})
            if isinstance(seller, dict):
                seller_id = str(seller.get('id', seller.get('name', ''))).lower()
            else:
                seller_id = str(seller).lower()

            resultados.append({
                'sku':        sku,
                'titulo':     titulo[:300],
                'preco_por':  preco_por,
                'preco_de':   preco_de,
                'desconto':   desconto,
                'url':        _gerar_link(url_produto, smttag),
                'imagem':     imagem,
                'seller':     seller_id,
            })

        except (ValueError, TypeError, KeyError):
            continue

    return resultados


def coletar() -> int:
    """Pipeline principal: busca, filtra e salva ofertas do Magalu."""
    if config.get('magalu_ativo', '0') != '1':
        log.info('ℹ️  Coleta Magalu desativada. Ative em Config → Magalu.')
        return 0

    smttag       = config.get('magalu_smttag', '')
    desconto_min = int(config.get('bot_desconto_minimo', '10') or '10')
    preco_max    = float(config.get('bot_preco_maximo', '500') or '500')

    if not smttag:
        log.warning('⚠️  magalu_smttag não configurado — links gerados sem rastreamento de afiliado!')

    log.info('🔍 Iniciando coleta Magalu...')
    log.info(f'   desconto_min={desconto_min}% | preco_max=R${preco_max:.0f} | smttag={smttag or "(vazio)"}')

    conn      = _db_conn()
    total     = 0
    ignorados = 0

    for i, keyword in enumerate(KEYWORDS_MAGALU):
        log.info(f'🏷️  Keyword: "{keyword}"')

        produtos = buscar_keyword(keyword, smttag, desconto_min, preco_max)
        log.info(f'   → {len(produtos)} produto(s) encontrado(s)')

        for p in produtos:
            pid = f"MGZ_{p['sku']}"

            if _na_blacklist(pid, conn):
                ignorados += 1
                continue

            if _ja_coletado(pid, conn):
                ignorados += 1
                continue

            try:
                conn.execute("""
                    INSERT INTO ofertas
                        (fonte, produto_id_externo, nome, preco_de, preco_por,
                         desconto_pct, url_afiliado, imagem_url, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'nova')
                """, (
                    'MGZ', pid, p['titulo'],
                    p['preco_de'], p['preco_por'], p['desconto'],
                    p['url'], p['imagem'],
                ))
                conn.commit()
                total += 1
                log.info(f'   ✅ {p["titulo"][:60]} — {p["desconto"]}% OFF — R${p["preco_por"]:.2f}')
            except sqlite3.Error as e:
                log.warning(f'   DB erro: {e}')

        # Pausa entre keywords (respeitar rate limit)
        if i < len(KEYWORDS_MAGALU) - 1:
            time.sleep(3)

    conn.close()
    log.info(f'✅ Magalu: {total} nova(s) | {ignorados} ignorada(s)')
    return total
