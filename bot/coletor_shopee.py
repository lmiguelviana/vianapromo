"""
coletor_shopee.py — Busca ofertas FITNESS na Shopee via API GraphQL de afiliados.

Fluxo:
  1. Autentica via SHA256 (app_id + timestamp + payload + app_secret)
  2. Busca produtos via productOfferV2 (GraphQL, por keyword)
  3. Gera links curtos de afiliado via generateShortLink
  4. Filtra por desconto mínimo, preço máximo e blacklist
  5. Deduplica e salva na tabela `ofertas`

Prefixo no banco: SHP_ (ex: SHP_itemId_shopId)
API: https://open-api.affiliate.shopee.com.br/graphql
Auth: SHA256 Credential={app_id}, Timestamp={ts}, Signature=sha256(app_id+ts+payload+secret)
Rate limit: 100 req/min — código 10030 quando excedido
"""
import sqlite3
import requests
import time
import re
import json
import sys
import os
import hashlib
import unicodedata

sys.path.insert(0, os.path.dirname(__file__))
import config
import dedup
import categorias

log = config.setup_logging('SHOPEE')

SHOPEE_API_URL = 'https://open-api.affiliate.shopee.com.br/graphql'

KEYWORDS_SHOPEE = [
    # Roupas fitness / academia (prioridade alta)
    'roupa para malhar feminina', 'roupa para malhar masculina',
    'roupa fitness feminina', 'roupa fitness masculina',
    'conjunto fitness feminino', 'conjunto fitness masculino',
    'conjunto academia feminino', 'conjunto academia masculino',
    'legging academia feminina', 'calca legging cintura alta',
    'shorts fitness feminino', 'short saia fitness',
    'bermuda fitness masculina', 'bermuda academia masculina',
    'camiseta dry fit academia', 'camiseta dry fit masculina',
    'regata academia masculina', 'top academia feminino',
    'top fitness feminino', 'macacao fitness feminino',
    'blusa dry fit feminina', 'jaqueta corta vento fitness',
    # Roupas para pedalar / ciclismo
    'roupa para pedalar', 'roupa ciclismo masculina', 'roupa ciclismo feminina',
    'camisa ciclismo masculina', 'camisa ciclismo feminina',
    'bermuda ciclismo acolchoada', 'short ciclismo feminino',
    'bretelle ciclismo masculino', 'macaquinho ciclismo feminino',
    'calca ciclismo feminina', 'luva ciclismo', 'oculos ciclismo',
    'capacete ciclismo', 'meia ciclismo',
    # Proteínas
    'whey protein', 'whey isolado', 'proteina isolada', 'albumina proteina',
    'hipercalorico massa', 'caseina proteina', 'whey concentrado',
    # Creatina
    'creatina monohidratada', 'creatina pura', 'creatina suplemento',
    # Pré-treino
    'pre treino', 'pre workout', 'termogenico academia',
    # Aminoácidos
    'bcaa aminoacido', 'glutamina pura', 'aminoacido esportivo',
    # Vitaminas e saúde
    'omega 3 capsulas', 'vitamina d suplemento', 'multivitaminico esportivo',
    'colageno hidrolisado', 'magnesio quelato', 'zinco suplemento',
    # Snacks fitness
    'pasta amendoim proteica', 'barra proteica', 'snack proteico',
    # Equipamentos musculação
    'haltere musculacao', 'anilha musculacao', 'kettlebell academia',
    'faixa elastica musculacao', 'corda pular fitness', 'step aerobico',
    'roda abdominal', 'bola pilates', 'caneleira academia',
    # Cardio
    'bicicleta ergometrica', 'esteira ergometrica',
    # Roupas fitness complementares
    'shorts musculacao',
    # Calçados
    'tenis academia', 'tenis crossfit',
    # Acessórios
    'coqueteleira academia', 'garrafa termica esportiva', 'luva musculacao',
    'munhequeira academia', 'cinto musculacao', 'bolsa academia',
    # Monitoramento
    'smartwatch esportivo', 'balanca bioimpedancia',
]


def _normalizar_nome(nome: str) -> str:
    n = unicodedata.normalize('NFKD', nome.lower()).encode('ascii', 'ignore').decode('ascii')
    n = re.sub(r'\b\d+[,.]?\d*\s*(kg|g|mg|ml|l|caps?|un|tabs?|comprimidos?)\b', '', n, flags=re.I)
    n = re.sub(
        r'\b(sabor|cor|tamanho|flavor|size|'
        r'chocolate|baunilha|morango|cookies?|maracuja|natural|caramel|caramelo|'
        r'limao|coco|manga|mango|abacaxi|cappuccino|cafe|banana|laranja|pistache|'
        r'neutro|red velvet|branco|preto|azul|verde|rosa|amarelo)\b',
        '', n, flags=re.I
    )
    n = re.sub(r'\b(pote|refil|pouch|balde|lata|caixa|sachet)\b', '', n, flags=re.I)
    n = re.sub(r'\b\d+%?\b', '', n)
    return re.sub(r'\s+', ' ', n).strip()


def _db_conn() -> sqlite3.Connection:
    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path, timeout=10)
    conn.execute('PRAGMA busy_timeout=10000')
    conn.execute('PRAGMA journal_mode=WAL')
    return conn


def _assinar(app_id: str, app_secret: str, timestamp: int, payload: str) -> str:
    """Assinatura SHA256 — string base = app_id + timestamp + payload + app_secret."""
    base = f"{app_id}{timestamp}{payload}{app_secret}"
    return hashlib.sha256(base.encode('utf-8')).hexdigest()


def _headers(app_id: str, app_secret: str, payload: str) -> dict:
    ts  = int(time.time())
    sig = _assinar(app_id, app_secret, ts, payload)
    return {
        'Content-Type':  'application/json',
        'Authorization': f'SHA256 Credential={app_id}, Timestamp={ts}, Signature={sig}',
    }


def _graphql(app_id: str, app_secret: str, query: str,
             variables: dict = None, operation: str = None) -> dict | None:
    """Executa uma query/mutation GraphQL na Shopee Affiliate API com retry."""
    body = {'query': query}
    if operation:
        body['operationName'] = operation
    if variables is not None:
        body['variables'] = variables

    # payload precisa ser idêntico na assinatura e no envio
    payload = json.dumps(body, separators=(',', ':'), ensure_ascii=False)
    headers = _headers(app_id, app_secret, payload)

    for tentativa in range(3):
        try:
            r = requests.post(
                SHOPEE_API_URL,
                headers=headers,
                data=payload.encode('utf-8'),
                timeout=20,
            )

            if r.status_code == 429:
                espera = 60 * (tentativa + 1)
                log.warning(f'  429 Shopee — aguardando {espera}s (tentativa {tentativa+1}/3)...')
                time.sleep(espera)
                continue

            if r.status_code >= 400:
                log.warning(f'  HTTP {r.status_code} Shopee: {r.text[:200]}')
                return None

            return r.json()

        except requests.RequestException as e:
            log.warning(f'  Erro de rede Shopee (tentativa {tentativa+1}/3): {e}')
            time.sleep(5)

    return None


def _gerar_link_afiliado(app_id: str, app_secret: str, url_produto: str) -> str:
    """Converte URL do produto em link curto de afiliado via generateShortLink."""
    url_safe   = url_produto.replace('"', '\\"')
    sub_ids    = json.dumps(['vianapromo', 'whatsapp'])

    query = (
        'mutation GenerateLink {\n'
        '  generateShortLink(input: {\n'
        f'    originUrl: "{url_safe}"\n'
        f'    subIds: {sub_ids}\n'
        '  }) {\n'
        '    shortLink\n'
        '  }\n'
        '}'
    )

    resp = _graphql(app_id, app_secret, query, variables={}, operation='GenerateLink')

    if resp and not resp.get('errors'):
        link = ((resp.get('data') or {}).get('generateShortLink') or {}).get('shortLink', '')
        if link:
            return link
    return url_produto


def buscar_keyword(app_id: str, app_secret: str, keyword: str,
                   desconto_min: int, preco_max: float, limite: int = 50) -> list[dict]:
    """Busca produtos na Shopee por palavra-chave via productOfferV2."""
    kw_safe = keyword.replace('"', '\\"')

    query = (
        'query Fetch($page: Int) {\n'
        '  productOfferV2(\n'
        '    listType: 0\n'
        '    sortType: 2\n'
        '    page: $page\n'
        f'    limit: {limite}\n'
        f'    keyword: "{kw_safe}"\n'
        '  ) {\n'
        '    nodes {\n'
        '      itemId\n'
        '      shopId\n'
        '      productName\n'
        '      priceMin\n'
        '      priceMax\n'
        '      price\n'
        '      commissionRate\n'
        '      sales\n'
        '      imageUrl\n'
        '      productLink\n'
        '      offerLink\n'
        '    }\n'
        '    pageInfo {\n'
        '      hasNextPage\n'
        '    }\n'
        '  }\n'
        '}'
    )

    resp = _graphql(app_id, app_secret, query, variables={'page': 0}, operation='Fetch')

    if not resp or resp.get('errors'):
        erros = (resp or {}).get('errors', [])
        if erros:
            log.warning(f'  Erro GraphQL para "{keyword}": {erros}')
        return []

    nodes = ((resp.get('data') or {}).get('productOfferV2') or {}).get('nodes', []) or []
    resultados = []

    for p in nodes:
        try:
            preco_por = float(p.get('priceMin') or p.get('price') or 0)
            preco_de  = float(p.get('priceMax') or 0)

            if preco_por <= 0 or preco_por > preco_max:
                continue
            if preco_de <= preco_por:
                continue

            desconto = int(((preco_de - preco_por) / preco_de) * 100)
            if desconto < desconto_min:
                continue

            item_id = str(p.get('itemId', '') or '')
            shop_id = str(p.get('shopId', '') or '')
            if not item_id or not shop_id:
                continue

            titulo = str(p.get('productName', '') or '').strip()
            if not titulo:
                continue

            url_produto = str(p.get('offerLink') or p.get('productLink') or '').strip()
            if not url_produto:
                url_produto = f"https://shopee.com.br/product/{shop_id}/{item_id}"

            resultados.append({
                'item_id':   item_id,
                'shop_id':   shop_id,
                'titulo':    titulo[:300],
                'preco_por': preco_por,
                'preco_de':  preco_de,
                'desconto':  desconto,
                'url':       url_produto,
                'imagem':    str(p.get('imageUrl', '') or ''),
            })

        except (ValueError, TypeError, KeyError) as e:
            log.debug(f'  Skip produto: {e}')
            continue

    return resultados


def coletar() -> int:
    """Pipeline principal: busca, filtra e salva ofertas da Shopee."""
    if config.get('shopee_ativo', '0') != '1':
        log.info('ℹ️  Coleta Shopee desativada. Ative em Config → Shopee.')
        return 0

    app_id     = config.get('shopee_app_id', '').strip()
    app_secret = config.get('shopee_app_secret', '').strip()

    if not app_id or not app_secret:
        log.warning('⚠️  shopee_app_id ou shopee_app_secret não configurados. Configure em Config → Shopee.')
        return 0

    desconto_min = int(config.get('bot_desconto_minimo', '10') or '10')
    preco_max    = float(config.get('bot_preco_maximo', '500') or '500')
    try:
        limite_passada = int(config.get('shopee_limite_por_passada', '50') or '50')
    except ValueError:
        limite_passada = 50
    limite_passada = max(1, min(1000, limite_passada))

    log.info('🛒 Iniciando coleta Shopee...')
    log.info(f'   APP_ID={app_id[:6]}*** | desconto_min={desconto_min}% | preco_max=R${preco_max:.0f} | limite={limite_passada}')

    conn      = _db_conn()
    total     = 0
    ignorados = 0

    for i, keyword in enumerate(KEYWORDS_SHOPEE):
        if total >= limite_passada:
            log.info(f'   Limite da passada atingido ({total}/{limite_passada}).')
            break

        log.info(f'🏷️  Keyword: "{keyword}"')
        limite_busca = min(50, max(1, limite_passada - total))
        produtos = buscar_keyword(app_id, app_secret, keyword, desconto_min, preco_max, limite=limite_busca)
        log.info(f'   → {len(produtos)} produto(s) encontrado(s)')

        for p in produtos:
            if total >= limite_passada:
                break

            pid       = f"SHP_{p['item_id']}_{p['shop_id']}"
            nome_norm = _normalizar_nome(p['titulo'])

            pular, motivo = dedup.deve_pular(conn, pid, p['preco_por'], nome_norm)
            if pular:
                ignorados += 1
                log.debug(f'  ⏭ {p["titulo"][:50]} — {motivo}')
                continue

            # Gera link curto de afiliado (0.7s entre chamadas para respeitar rate limit)
            link_afiliado = _gerar_link_afiliado(app_id, app_secret, p['url'])
            time.sleep(0.7)

            try:
                conn.execute('BEGIN IMMEDIATE')
                # Double-check dedup após obter lock
                pular, motivo = dedup.deve_pular(conn, pid, p['preco_por'], nome_norm)
                if pular:
                    conn.rollback()
                    ignorados += 1
                    continue

                categoria = categorias.detectar_categoria(p['titulo'])
                conn.execute("""
                    INSERT INTO ofertas
                        (fonte, produto_id_externo, nome, nome_norm, categoria,
                         preco_de, preco_por, desconto_pct, url_afiliado, imagem_url, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nova')
                """, (
                    'SHP', pid, p['titulo'], nome_norm, categoria,
                    p['preco_de'], p['preco_por'], p['desconto'],
                    link_afiliado, p['imagem'],
                ))
                conn.commit()
                total += 1
                log.info(f'   ✅ {p["titulo"][:60]} — {p["desconto"]}% OFF — R${p["preco_por"]:.2f}')
            except sqlite3.Error as e:
                conn.rollback()
                log.warning(f'   DB erro: {e}')

        if i < len(KEYWORDS_SHOPEE) - 1:
            time.sleep(2)

    conn.close()
    log.info(f'✅ Shopee: {total} nova(s) | {ignorados} ignorada(s)')
    return total
