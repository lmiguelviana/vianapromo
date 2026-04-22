"""
coletor.py — Busca ofertas FITNESS via API do Mercado Livre.

Fluxo confirmado e funcional (2026):
  1. Token de usuário (Authorization Code) com auto-refresh automático
  2. [Fonte 1] Product IDs de highlights fitness via /highlights/MLB/category/MLB1276
  3. [Fonte 2] Product IDs por palavras-chave fitness via /products/search
  4. Para cada produto: preço/desconto via /products/{pid}/items
  5. Nome e imagem via /products/{pid}
  6. Filtra, deduplica e salva na tabela `ofertas`
"""
import sqlite3
import requests
import time
import sys
import os
import socket

# Timeout global — nenhuma chamada de rede pode travar mais que 20s
socket.setdefaulttimeout(20)

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('COLETOR')

# ── Fontes de produto ─────────────────────────────────────────────────────────

# Categorias com highlights CONFIRMADOS para fitness
CATEGORIAS_HIGHLIGHTS = [
    ('MLB1276', 'Esportes e Fitness'),  # Confirmado: suplementos, whey, creatina
]

# Palavras-chave fitness/academia/saúde para busca complementar via /products/search
KEYWORDS_FITNESS = [
    # Suplementos
    'whey protein',
    'creatina',
    'pre treino',
    'bcaa aminoacido',
    'colageno hidrolisado',
    'vitamina d3',
    'omega 3',
    'glutamina',
    'proteina vegana',
    'hipercalorico massa muscular',
    # Academia / Musculação
    'haltere musculacao',
    'barra de supino',
    'kettlebell',
    'faixa elastica treino',
    'luva musculacao',
    'cinto musculacao',
    'corda pular fitness',
    'step aerobico',
    # Roupas e Calçados
    'legging fitness feminina',
    'shorts academia masculino',
    'top academia feminino',
    'tenis corrida masculino',
    'tenis corrida feminino',
    # Vida saudável
    'shakeira coqueteleira',
    'balanca bioimpedancia',
    'monitor frequencia cardiaca',
    'tapete yoga pilates',
    'foam roller massagem',
    'cinto corrida hidratacao',
]

ML_TOKEN_URL      = 'https://api.mercadolibre.com/oauth/token'
ML_HIGHLIGHTS_URL = 'https://api.mercadolibre.com/highlights/MLB/category/{cat}'
ML_PRODUCT_SEARCH = 'https://api.mercadolibre.com/products/search'
ML_PRODUCT_URL    = 'https://api.mercadolibre.com/products/{pid}'
ML_PROD_ITEMS_URL = 'https://api.mercadolibre.com/products/{pid}/items'


# ── Autenticação ─────────────────────────────────────────────────────────────

def _salvar_tokens(access: str, refresh: str, expires_in: int) -> None:
    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path)
    for k, v in [
        ('ml_access_token',  access),
        ('ml_refresh_token', refresh),
        ('ml_token_expires', str(int(time.time()) + expires_in)),
    ]:
        conn.execute("INSERT OR REPLACE INTO config (chave, valor) VALUES (?, ?)", (k, v))
    conn.commit()
    conn.close()


def obter_token() -> str | None:
    """Token de usuário com auto-refresh via refresh_token."""
    access_token  = config.get('ml_access_token', '')
    refresh_token = config.get('ml_refresh_token', '')
    expires_at    = int(config.get('ml_token_expires', '0'))
    client_id     = config.get('ml_client_id', '')
    client_secret = config.get('ml_client_secret', '')

    if access_token and expires_at > time.time() + 300:
        log.info('🔑 Token ML carregado (válido)')
        return access_token

    if refresh_token and client_id and client_secret:
        log.info('🔄 Renovando token ML...')
        try:
            r = requests.post(ML_TOKEN_URL, data={
                'grant_type':    'refresh_token',
                'client_id':     client_id,
                'client_secret': client_secret,
                'refresh_token': refresh_token,
            }, timeout=15)
            r.raise_for_status()
            data = r.json()
            _salvar_tokens(
                data['access_token'],
                data.get('refresh_token', refresh_token),
                int(data.get('expires_in', 21600)),
            )
            log.info('✅ Token ML renovado')
            return data['access_token']
        except Exception as e:
            log.error(f'Falha ao renovar token: {e}')

    log.error('❌ Token expirado. Acesse /viana/config → "Conectar Conta ML".')
    return None


# ── Coleta de product IDs ─────────────────────────────────────────────────────

def buscar_product_ids_highlights(cat_id: str, token: str) -> list[str]:
    """Product IDs em destaque para a categoria."""
    try:
        r = requests.get(
            ML_HIGHLIGHTS_URL.format(cat=cat_id),
            headers={'Authorization': f'Bearer {token}'},
            timeout=15,
        )
        r.raise_for_status()
        ids = [x['id'] for x in r.json().get('content', []) if 'id' in x]
        log.info(f'   → {len(ids)} produtos em destaque')
        return ids
    except Exception as e:
        log.warning(f'   Highlights indisponível para {cat_id}: {e}')
        return []


def buscar_product_ids_keyword(keyword: str, token: str, limite: int = 10) -> list[str]:
    """Product IDs via busca por palavra-chave fitness."""
    try:
        r = requests.get(
            ML_PRODUCT_SEARCH,
            params={'site_id': 'MLB', 'q': keyword, 'limit': limite},
            headers={'Authorization': f'Bearer {token}'},
            timeout=15,
        )
        r.raise_for_status()
        ids = [x['id'] for x in r.json().get('results', []) if 'id' in x]
        log.info(f'   → {len(ids)} produtos encontrados')
        return ids
    except Exception as e:
        log.warning(f'   Erro na busca por "{keyword}": {e}')
        return []


# ── Detalhes do produto ───────────────────────────────────────────────────────

def buscar_produto(product_id: str, token: str) -> dict | None:
    """Busca nome e imagem do produto no catálogo ML."""
    try:
        r = requests.get(
            ML_PRODUCT_URL.format(pid=product_id),
            headers={'Authorization': f'Bearer {token}'},
            timeout=10,
        )
        if r.status_code == 200:
            return r.json()
    except Exception:
        pass
    return None


def buscar_melhor_anuncio(product_id: str, token: str) -> dict | None:
    """Busca o anúncio com maior desconto para o produto (condição=new)."""
    try:
        r = requests.get(
            ML_PROD_ITEMS_URL.format(pid=product_id),
            params={'limit': 20},
            headers={'Authorization': f'Bearer {token}'},
            timeout=15,
        )
        r.raise_for_status()

        melhor = None
        melhor_desc = 0

        for a in r.json().get('results', []):
            price = float(a.get('price') or 0)
            orig  = float(a.get('original_price') or 0)
            if price <= 0 or orig <= price or a.get('condition') != 'new':
                continue
            desc = int(round((orig - price) / orig * 100))
            if desc > melhor_desc:
                melhor_desc = desc
                melhor = {'price': price, 'original_price': orig, 'desconto': desc}

        return melhor
    except Exception as e:
        log.debug(f'Erro ao buscar anúncios de {product_id}: {e}')
        return None


# ── Helpers de banco ──────────────────────────────────────────────────────────

def ja_coletado(conn: sqlite3.Connection, produto_id: str) -> bool:
    """True se está na blacklist (rejeitado) OU coletado nas últimas 48h."""
    try:
        if conn.execute("SELECT 1 FROM blacklist WHERE produto_id_externo = ?", (produto_id,)).fetchone():
            return True
    except sqlite3.OperationalError:
        pass  # tabela blacklist ainda não existe
    return conn.execute(
        """SELECT id FROM ofertas
           WHERE produto_id_externo = ?
             AND coletado_em >= datetime('now', '-48 hours', 'localtime')""",
        (produto_id,)
    ).fetchone() is not None


def salvar_oferta(conn, product_id, nome, preco_de, preco_por,
                  desconto, url_afiliado, imagem_url) -> None:
    conn.execute(
        """INSERT INTO ofertas
           (fonte, produto_id_externo, nome, preco_de, preco_por,
            desconto_pct, url_afiliado, imagem_url, status)
           VALUES ('ML', ?, ?, ?, ?, ?, ?, ?, 'nova')""",
        (product_id, nome, preco_de, preco_por, desconto, url_afiliado, imagem_url)
    )
    log.info(f'  ✅ {nome[:55]} — {desconto}% OFF — R${preco_por:.2f}')


# ── Processamento de produto individual ──────────────────────────────────────

def _processar_produto(conn, prod_id: str, token: str,
                       partner_id: str, desconto_min: int, preco_max: float) -> int:
    """Tenta salvar o produto se tiver desconto válido. Retorna 1 se salvo."""
    if ja_coletado(conn, prod_id):
        return 0

    anuncio = buscar_melhor_anuncio(prod_id, token)
    if not anuncio:
        return 0

    preco_por = anuncio['price']
    preco_de  = anuncio['original_price']
    desconto  = anuncio['desconto']

    if desconto < desconto_min or preco_por > preco_max:
        return 0

    produto = buscar_produto(prod_id, token)
    if not produto:
        return 0

    nome       = (produto.get('name') or '').strip()
    pictures   = produto.get('pictures', [])
    imagem_url = pictures[0]['url'].replace('-F.jpg', '-O.jpg') if pictures else ''
    permalink  = f'https://www.mercadolivre.com.br/p/{prod_id}'
    url_afiliado = f'{permalink}?partner_id={partner_id}' if partner_id else permalink

    salvar_oferta(conn, prod_id, nome, preco_de, preco_por, desconto, url_afiliado, imagem_url)
    return 1


# ── Pipeline principal ────────────────────────────────────────────────────────

def coletar() -> int:
    partner_id   = config.get('ml_partner_id', '')
    desconto_min = int(config.get('bot_desconto_minimo', '10'))
    preco_max    = float(config.get('bot_preco_maximo', '500'))

    if not partner_id:
        log.warning('ML Partner ID não configurado — links sem rastreamento')

    token = obter_token()
    if not token:
        return 0

    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path, timeout=10)
    conn.execute('PRAGMA journal_mode=WAL')
    conn.execute('PRAGMA busy_timeout=10000')

    # Garante que a tabela blacklist existe (pode não ter sido criada pelo PHP ainda)
    conn.execute("""
        CREATE TABLE IF NOT EXISTS blacklist (
            produto_id_externo TEXT PRIMARY KEY,
            motivo TEXT NOT NULL DEFAULT 'rejeitado',
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        )
    """)

    # Migra rejeições antigas para a blacklist (produtos rejeitados antes da blacklist existir)
    conn.execute("""
        INSERT OR IGNORE INTO blacklist (produto_id_externo, motivo)
        SELECT produto_id_externo, 'rejeitado'
        FROM ofertas
        WHERE status = 'rejeitada' AND produto_id_externo != ''
    """)
    conn.commit()
    migrados = conn.execute("SELECT COUNT(*) FROM blacklist").fetchone()[0]
    if migrados:
        log.info(f'🚫 Blacklist: {migrados} produto(s) bloqueado(s)')

    total_salvas = 0

    # Fonte 1: Highlights da categoria Esportes e Fitness
    for cat_id, cat_nome in CATEGORIAS_HIGHLIGHTS:
        log.info(f'🔍 Highlights: {cat_nome} ({cat_id})')
        ids = buscar_product_ids_highlights(cat_id, token)
        for prod_id in ids:
            total_salvas += _processar_produto(conn, prod_id, token, partner_id, desconto_min, preco_max)
        conn.commit()  # libera lock após cada categoria

    # Fonte 2: Palavras-chave fitness específicas
    for i, keyword in enumerate(KEYWORDS_FITNESS):
        log.info(f'🏋️  Keyword: "{keyword}"')
        ids = buscar_product_ids_keyword(keyword, token)
        for prod_id in ids:
            total_salvas += _processar_produto(conn, prod_id, token, partner_id, desconto_min, preco_max)
        conn.commit()         # libera lock após cada keyword
        time.sleep(0.5)       # pausa leve para não saturar a CPU/rede

    conn.close()
    log.info(f'✔ Coleta concluída — {total_salvas} novas ofertas FITNESS salvas')
    return total_salvas


if __name__ == '__main__':
    coletar()
