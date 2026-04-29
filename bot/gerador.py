"""
gerador.py — Gera texto de vendas para WhatsApp.

Dois modos controlados pelo Config:
  usar_ia=1  → chama OpenRouter para texto personalizado por produto
  usar_ia=0  → usa template fixo com variáveis substituídas automaticamente
"""
import sqlite3
import sys
import os

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('GERADOR')

# Emojis por categoria de produto (detectado pelo nome)
EMOJI_MAP = {
    'whey':       '🥤', 'proteina': '💪', 'creatina': '⚡',
    'pre-treino': '🔥', 'pre treino': '🔥', 'vitamina': '🌿', 'colageno': '✨',
    'luva':       '🥊', 'cinto':    '🏋️', 'haltere':  '🏋️',
    'esteira':    '🏃', 'bicicleta':'🚴', 'roupa':    '👕',
    'shorts':     '👟', 'tenis':    '👟', 'legging':  '👟',
    'bcaa':       '💊', 'omega':    '🌿', 'glutamina':'💊',
}

def detectar_emoji(nome: str) -> str:
    nome_lower = nome.lower()
    for palavra, emoji in EMOJI_MAP.items():
        if palavra in nome_lower:
            return emoji
    return '🏋️'


# ── Modo Template (sem IA) ────────────────────────────────────────────────────

TEMPLATE_PADRAO = (
    "{EMOJI} *{NOME}*\n\n"
    "~~R$ {PRECO_DE}~~ por apenas *R$ {PRECO_POR}* 🏷️ *{DESCONTO}% OFF*\n\n"
    "🔗 link de afiliado — comprar por aqui me ajuda sem custo extra pra você\n"
    "👉 {LINK}"
)


def gerar_texto_template(oferta: dict) -> str:
    """Gera mensagem usando template fixo com variáveis substituídas."""
    template = config.get('mensagem_padrao', '') or TEMPLATE_PADRAO

    preco_de  = f"{oferta['preco_de']:.2f}".replace('.', ',') if oferta['preco_de'] > 0 else ''
    preco_por = f"{oferta['preco_por']:.2f}".replace('.', ',')
    emoji     = detectar_emoji(oferta['nome'])

    texto = template
    texto = texto.replace('{NOME}',      oferta['nome'])
    texto = texto.replace('{PRECO_DE}',  preco_de)
    texto = texto.replace('{PRECO_POR}', preco_por)
    texto = texto.replace('{DESCONTO}',  str(oferta['desconto_pct']))
    texto = texto.replace('{EMOJI}',     emoji)
    texto = texto.replace('{LINK}',      '{LINK}')  # mantém placeholder para o emissor

    return texto


# ── Modo IA (OpenRouter) ──────────────────────────────────────────────────────

def montar_prompt(oferta: dict) -> str:
    emoji = detectar_emoji(oferta['nome'])
    preco_de_str  = f"R$ {oferta['preco_de']:.2f}".replace('.', ',') if oferta['preco_de'] > 0 else ''
    preco_por_str = f"R$ {oferta['preco_por']:.2f}".replace('.', ',')

    secao_preco = (
        f"Preço original: {preco_de_str}\nPreço atual: {preco_por_str}\nDesconto: {oferta['desconto_pct']}%"
        if preco_de_str else f"Preço: {preco_por_str}"
    )

    return f"""Você é um copywriter especializado em marketing de afiliados fitness no Brasil.
Gere uma mensagem CURTA para WhatsApp promovendo este produto:

Produto: {oferta['nome']}
{secao_preco}
Emoji sugerido: {emoji}

REGRAS OBRIGATÓRIAS (não quebre nenhuma):
- Máximo 5 linhas no total
- Use *negrito* do WhatsApp para o nome do produto e o desconto
- Use ~~riscado~~ para o preço original (se houver)
- 2 a 3 emojis relevantes ao produto — nada mais
- Tom empolgante mas honesto — NÃO invente características
- Português brasileiro informal
- Última linha SEMPRE: "👉 {{LINK}}"
- Penúltima linha: "🔗 link de afiliado — comprar por aqui me ajuda sem custo extra pra você"
- NÃO inclua o link real — escreva exatamente "{{LINK}}" como placeholder"""


def gerar_texto_ia(oferta: dict, cliente, modelo: str) -> str | None:
    """Chama o OpenRouter e retorna o texto gerado, ou None em caso de erro."""
    try:
        resp = cliente.chat.completions.create(
            model=modelo,
            messages=[{'role': 'user', 'content': montar_prompt(oferta)}],
            max_tokens=300,
            temperature=0.8,
        )
        # content pode ser None em respostas válidas do OpenRouter
        content = resp.choices[0].message.content
        return content.strip() if content else None
    except Exception as e:
        # Tenta extrair detalhes do erro HTTP (429 rate limit, 401 auth, 503 etc)
        status = getattr(getattr(e, 'response', None), 'status_code', None)
        body   = ''
        try:
            body = e.response.text[:300]  # type: ignore[union-attr]
        except Exception:
            pass
        if status:
            log.error(f'Erro OpenRouter HTTP {status} (oferta {oferta["id"]}): {body}')
            if status == 429:
                log.error('  ↳ Rate limit atingido! Modelos :free = 50 req/dia (sem créditos) ou 1000 req/dia (com $10+ em créditos).')
            elif status == 401:
                log.error('  ↳ API Key inválida ou sem permissão.')
        else:
            log.error(f'Erro OpenRouter (oferta {oferta["id"]}): {e}')
        return None


# ── Pipeline principal ────────────────────────────────────────────────────────

def _where_fonte(fonte: str | None) -> tuple[str, tuple]:
    if fonte == 'ml':
        return " AND fonte IN ('ML', 'MGZ')", ()
    if fonte == 'shopee':
        return " AND fonte = 'SHP'", ()
    return "", ()


def gerar_todas(fonte: str | None = None) -> int:
    """Processa ofertas novas da fonte informada. Retorna quantas foram geradas."""
    usar_ia = config.get('usar_ia', '1') != '0'
    modelo  = config.get('openrouter_model', 'minimax/minimax-01:free')
    apikey  = config.get('openrouter_apikey', '')

    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path, timeout=10)
    conn.row_factory = sqlite3.Row
    conn.execute('PRAGMA busy_timeout=10000')  # ANTES do journal_mode
    conn.execute('PRAGMA journal_mode=WAL')

    filtro_fonte, params = _where_fonte(fonte)
    ofertas = conn.execute(
        f"SELECT * FROM ofertas WHERE status = 'nova'{filtro_fonte} ORDER BY desconto_pct DESC",
        params
    ).fetchall()

    if not ofertas:
        sufixo = f' ({fonte})' if fonte else ''
        log.info(f'Nenhuma oferta nova para processar{sufixo}.')
        conn.close()
        return 0

    cliente = None
    if usar_ia:
        if not apikey:
            log.warning('⚠️ usar_ia=1 mas OpenRouter API Key não configurada — usando template')
            usar_ia = False
        else:
            try:
                from openai import OpenAI
                cliente = OpenAI(base_url='https://openrouter.ai/api/v1', api_key=apikey)
                log.info(f'📝 Gerando texto com IA — modelo: {modelo}')
            except ImportError:
                log.warning('openai não instalado — usando template')
                usar_ia = False

    if not usar_ia:
        log.info('📝 Gerando texto com template padrão (IA desligada)')

    geradas = 0
    for oferta in ofertas:
        od = dict(oferta)
        log.info(f'  → {od["nome"][:60]}')

        if usar_ia and cliente:
            texto = gerar_texto_ia(od, cliente, modelo)
        else:
            texto = gerar_texto_template(od)

        if texto:
            conn.execute(
                "UPDATE ofertas SET mensagem_ia = ?, status = 'pronta' WHERE id = ?",
                (texto, od['id'])
            )
            geradas += 1
            log.info(f'  ✅ Texto gerado ({len(texto)} chars)')
        else:
            conn.execute("UPDATE ofertas SET status = 'erro_ia' WHERE id = ?", (od['id'],))

    conn.commit()
    conn.close()
    log.info(f'✔ {geradas}/{len(ofertas)} textos gerados')
    return geradas


if __name__ == '__main__':
    gerar_todas()
