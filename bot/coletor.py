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
import re
import unicodedata

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
    'whey isolado',
    'creatina',
    'pre treino',
    'bcaa aminoacido',
    'colageno hidrolisado',
    'vitamina d3',
    'omega 3',
    'glutamina',
    'proteina vegana',
    'hipercalorico massa muscular',
    'albumina proteina',
    'termogenico emagrecedor',
    'multivitaminico esportivo',
    'cafeina anidra',
    # Academia / Musculação
    'haltere musculacao',
    'anilha musculacao',
    'barra de supino',
    'kettlebell',
    'faixa elastica treino',
    'elastico resistencia musculacao',
    'luva musculacao',
    'cinto musculacao',
    'munhequeira musculacao',
    'joelheira esportiva',
    'corda pular fitness',
    'step aerobico',
    'roda abdominal exercicio',
    'suporte paralela dip',
    # Roupas e Calçados Fitness
    'legging fitness feminina',
    'legging academia cintura alta',
    'calca legging compressao',
    'conjunto academia feminino',
    'shorts academia masculino',
    'bermuda treino masculino',
    'calca jogger masculino',
    'top academia feminino',
    'sutia esportivo academia',
    'camiseta dry fit masculino',
    'regata masculina academia',
    'camiseta compressao masculino',
    'blusa moletom treino feminino',
    'jaqueta corta vento corrida',
    'tenis corrida masculino',
    'tenis corrida feminino',
    'tenis academia feminino',
    'tenis crossfit masculino',
    'meia esportiva cano longo',
    'kit roupa academia feminina',
    # Vida saudável / Acessórios
    'shakeira coqueteleira',
    'garrafa termica esportiva',
    'balanca bioimpedancia',
    'monitor frequencia cardiaca',
    'tapete yoga pilates',
    'foam roller massagem',
    'cinto corrida hidratacao',
    'bolsa academia fitness',
    # Equipamentos de cardio / máquinas
    'esteira ergometrica',
    'bicicleta ergometrica',
    'bicicleta spinning',
    'eliptico ergometrico',
    'remo ergometrico',
    'escada ergometrica',
    # Bancos e suportes
    'banco supino musculacao',
    'banco de exercicios dobravel',
    'rack barra musculacao',
    'suporte haltere academia',
    'torre de musculacao',
    # Barras e pesos
    'barra olimpica',
    'barra reta musculacao',
    'placa peso olimpica',
    'caneleira de peso',
    'colete de peso',
    # Proteção articular
    'tornozeleira academia',
    'cotoveleira esportiva',
    'bandagem elastica esportiva',
    # Equipamentos funcionais
    'bola pilates suica',
    'mini band circulo elastico',
    'bosu fitness',
    'escada agilidade funcional',
    'cones treino funcional',
    'slam ball medicine ball',
    # Nutrição / Alimentação saudável
    'pasta amendoim proteica',
    'barra proteica',
    'aveia flocos',
    'whey bar proteica',
    # Acessórios extra
    'suporte celular bicicleta',
    'relogio smartwatch esportivo',
    'fone ouvido esporte',
]

ML_TOKEN_URL      = 'https://api.mercadolibre.com/oauth/token'
ML_HIGHLIGHTS_URL = 'https://api.mercadolibre.com/highlights/MLB/category/{cat}'
ML_PRODUCT_SEARCH = 'https://api.mercadolibre.com/products/search'
ML_PRODUCT_URL    = 'https://api.mercadolibre.com/products/{pid}'
ML_PROD_ITEMS_URL = 'https://api.mercadolibre.com/products/{pid}/items'


# ── Autenticação ─────────────────────────────────────────────────────────────

def _salvar_tokens(access: str, refresh: str, expires_in: int) -> None:
    """Salva tokens no banco com retry — crítico não perder o refresh_token rotacionado."""
    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    for tentativa in range(5):
        try:
            conn = sqlite3.connect(db_path, timeout=15)
            conn.execute('PRAGMA busy_timeout=15000')
            conn.execute('PRAGMA journal_mode=WAL')
            for k, v in [
                ('ml_access_token',  access),
                ('ml_refresh_token', refresh),
                ('ml_token_expires', str(int(time.time()) + expires_in)),
            ]:
                conn.execute("INSERT OR REPLACE INTO config (chave, valor) VALUES (?, ?)", (k, v))
            conn.commit()
            conn.close()
            return
        except sqlite3.OperationalError as e:
            log.warning(f'_salvar_tokens tentativa {tentativa+1}/5 falhou: {e}')
            time.sleep(2 ** tentativa)
    log.error('CRÍTICO: não foi possível salvar tokens ML no banco após 5 tentativas!')


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
        for tentativa in range(3):
            try:
                r = requests.post(ML_TOKEN_URL, data={
                    'grant_type':    'refresh_token',
                    'client_id':     client_id,
                    'client_secret': client_secret,
                    'refresh_token': refresh_token,
                }, timeout=15)
                r.raise_for_status()
                data = r.json()
                new_refresh = data.get('refresh_token') or refresh_token
                _salvar_tokens(
                    data['access_token'],
                    new_refresh,
                    int(data.get('expires_in', 21600)),
                )
                log.info('✅ Token ML renovado')
                return data['access_token']
            except requests.RequestException as e:
                log.warning(f'Falha ao renovar token (tentativa {tentativa+1}/3): {e}')
                if tentativa < 2:
                    time.sleep(5 * (tentativa + 1))
        log.error('❌ Falha ao renovar token ML após 3 tentativas.')

    log.error('❌ Token expirado. Acesse o painel → Config → "Conectar Conta ML".')
    return None


# ── Coleta de product IDs ─────────────────────────────────────────────────────

def buscar_product_ids_highlights(cat_id: str, token: str) -> list[str]:
    """Product IDs em destaque para a categoria."""
    for tentativa in range(2):
        try:
            r = requests.get(
                ML_HIGHLIGHTS_URL.format(cat=cat_id),
                headers={'Authorization': f'Bearer {token}'},
                timeout=15,
            )
            if r.status_code == 429:
                espera = 60 * (tentativa + 1)
                log.warning(f'   429 em highlights {cat_id} — aguardando {espera}s...')
                time.sleep(espera)
                continue
            r.raise_for_status()
            ids = [x['id'] for x in r.json().get('content', []) if 'id' in x]
            log.info(f'   → {len(ids)} produtos em destaque')
            return ids
        except Exception as e:
            log.warning(f'   Highlights indisponível para {cat_id}: {e}')
            return []
    return []


def buscar_product_ids_keyword(keyword: str, token: str, limite: int = 20) -> list[str]:
    """Product IDs via busca por palavra-chave fitness. Retry automático em 429."""
    for tentativa in range(3):
        try:
            r = requests.get(
                ML_PRODUCT_SEARCH,
                params={'site_id': 'MLB', 'q': keyword, 'limit': limite},
                headers={'Authorization': f'Bearer {token}'},
                timeout=15,
            )
            if r.status_code == 429:
                espera = 60 * (tentativa + 1)  # 60s, 120s, 180s
                log.warning(f'   429 em "{keyword}" — aguardando {espera}s (tentativa {tentativa+1}/3)...')
                time.sleep(espera)
                continue
            r.raise_for_status()
            ids = [x['id'] for x in r.json().get('results', []) if 'id' in x]
            log.info(f'   → {len(ids)} produtos encontrados')
            return ids
        except Exception as e:
            log.warning(f'   Erro na busca por "{keyword}": {e}')
            return []
    log.warning(f'   Desistindo de "{keyword}" após 3 tentativas com rate limit.')
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

def _normalizar_nome(nome: str) -> str:
    """Remove variações (sabor, cor, peso) do nome para dedup de produtos similares."""
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


def ja_coletado(conn: sqlite3.Connection, produto_id: str, preco_por: float = None) -> bool:
    """True se está na blacklist OU já foi coletado com este mesmo preço antes."""
    try:
        if conn.execute("SELECT 1 FROM blacklist WHERE produto_id_externo = ?", (produto_id,)).fetchone():
            return True
    except sqlite3.OperationalError:
        pass
    if preco_por is not None:
        # Mesmo produto, mesmo preço → não coletar de novo (preço não mudou)
        return conn.execute(
            "SELECT 1 FROM ofertas WHERE produto_id_externo = ? AND preco_por = ?",
            (produto_id, preco_por)
        ).fetchone() is not None
    # Fallback sem preço: usa janela 48h
    return conn.execute(
        "SELECT 1 FROM ofertas WHERE produto_id_externo = ? "
        "AND coletado_em >= datetime('now', '-48 hours', 'localtime')",
        (produto_id,)
    ).fetchone() is not None


def salvar_oferta(conn, product_id, nome, nome_norm, preco_de, preco_por,
                  desconto, url_afiliado, imagem_url) -> None:
    conn.execute(
        """INSERT INTO ofertas
           (fonte, produto_id_externo, nome, nome_norm, preco_de, preco_por,
            desconto_pct, url_afiliado, imagem_url, status)
           VALUES ('ML', ?, ?, ?, ?, ?, ?, ?, ?, 'nova')""",
        (product_id, nome, nome_norm, preco_de, preco_por, desconto, url_afiliado, imagem_url)
    )
    log.info(f'  ✅ {nome[:55]} — {desconto}% OFF — R${preco_por:.2f}')


# ── Processamento de produto individual ──────────────────────────────────────

def _processar_produto(conn, prod_id: str, token: str,
                       partner_id: str, desconto_min: int, preco_max: float) -> int:
    """Tenta salvar o produto se tiver desconto válido. Retorna 1 se salvo."""
    anuncio = buscar_melhor_anuncio(prod_id, token)
    if not anuncio:
        return 0

    preco_por = anuncio['price']
    preco_de  = anuncio['original_price']
    desconto  = anuncio['desconto']

    if desconto < desconto_min or preco_por > preco_max:
        return 0

    # Rápido: blacklist + mesmo produto/preço (sem chamar API de produto)
    if ja_coletado(conn, prod_id, preco_por):
        return 0

    produto = buscar_produto(prod_id, token)
    if not produto:
        return 0

    nome      = (produto.get('name') or '').strip()
    nome_norm = _normalizar_nome(nome)

    # Dedup por nome normalizado: bloqueia variações de sabor/cor/tamanho por 7 dias
    if nome_norm and conn.execute(
        "SELECT 1 FROM ofertas WHERE nome_norm = ? AND nome_norm != '' "
        "AND coletado_em > datetime('now', '-7 days', 'localtime')",
        (nome_norm,)
    ).fetchone():
        log.debug(f'  ⏭ Variação já coletada: {nome[:50]}')
        return 0

    pictures     = produto.get('pictures', [])
    imagem_url   = pictures[0]['url'].replace('-F.jpg', '-O.jpg') if pictures else ''
    permalink    = f'https://www.mercadolivre.com.br/p/{prod_id}'
    url_afiliado = f'{permalink}?partner_id={partner_id}' if partner_id else permalink

    salvar_oferta(conn, prod_id, nome, nome_norm, preco_de, preco_por, desconto, url_afiliado, imagem_url)
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
            time.sleep(0.3)
        conn.commit()  # libera lock após cada categoria

    # Fonte 2: Palavras-chave fitness específicas
    for i, keyword in enumerate(KEYWORDS_FITNESS):
        log.info(f'🏋️  Keyword: "{keyword}"')
        ids = buscar_product_ids_keyword(keyword, token)
        for prod_id in ids:
            total_salvas += _processar_produto(conn, prod_id, token, partner_id, desconto_min, preco_max)
            time.sleep(0.3)   # pausa entre produtos da mesma keyword
        conn.commit()         # libera lock após cada keyword
        time.sleep(2)         # respeita rate limit da ML API (~30 req/min por endpoint)

    conn.close()
    log.info(f'✔ Coleta concluída — {total_salvas} novas ofertas FITNESS salvas')
    return total_salvas


if __name__ == '__main__':
    coletar()
