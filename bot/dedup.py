"""
dedup.py — Lógica centralizada de deduplicação para os 3 coletores (ML, Magalu, Shopee).

Regras (em ordem):
  1. Blacklist permanente → bloqueia sempre
  2. Mesmo produto + mesmo preço EXATO → bloqueia (cache permanente)
  3. Produto JÁ ENVIADO nos últimos N dias → bloqueia, exceto se preço caiu Y% ou mais
  4. Variação do mesmo produto (nome_norm) coletada nos últimos 14d → bloqueia
"""
import sqlite3
import sys
import os

sys.path.insert(0, os.path.dirname(__file__))
import config


def _get_int(chave: str, default: int) -> int:
    try:
        return int(config.get(chave, str(default)) or default)
    except (ValueError, TypeError):
        return default


def _get_float(chave: str, default: float) -> float:
    try:
        return float(config.get(chave, str(default)) or default)
    except (ValueError, TypeError):
        return default


def na_blacklist(conn: sqlite3.Connection, produto_id: str) -> bool:
    try:
        return conn.execute(
            "SELECT 1 FROM blacklist WHERE produto_id_externo = ?", (produto_id,)
        ).fetchone() is not None
    except sqlite3.OperationalError:
        return False


def deve_pular(conn: sqlite3.Connection, produto_id: str,
               preco_por: float = None, nome_norm: str = None) -> tuple[bool, str]:
    """
    Decide se deve pular este produto na coleta.
    Retorna (pular: bool, motivo: str).

    Configs lidas do banco:
      - bot_dias_min_reenvio (default 30): janela de bloqueio após envio
      - bot_queda_minima_pct (default 5):  queda mínima de preço para reenviar
    """
    dias_min  = _get_int('bot_dias_min_reenvio', 30)
    queda_min = _get_float('bot_queda_minima_pct', 5.0)

    # Regra 1: blacklist permanente
    if na_blacklist(conn, produto_id):
        return True, 'blacklist'

    # Regra 2: mesmo produto + mesmo preço EXATO (cache permanente)
    if preco_por is not None:
        if conn.execute(
            "SELECT 1 FROM ofertas WHERE produto_id_externo = ? AND preco_por = ?",
            (produto_id, preco_por)
        ).fetchone():
            return True, 'preço idêntico já coletado'

    # Regra 3: produto JÁ ENVIADO recentemente
    last_sent = conn.execute("""
        SELECT preco_por,
               julianday('now', 'localtime') - julianday(enviado_em) AS dias_ago
        FROM ofertas
        WHERE produto_id_externo = ?
          AND status = 'enviada'
          AND enviado_em IS NOT NULL
        ORDER BY enviado_em DESC LIMIT 1
    """, (produto_id,)).fetchone()

    if last_sent:
        last_price, dias_ago = last_sent[0], (last_sent[1] or 0)

        if dias_ago < dias_min:
            return True, f'enviado há {dias_ago:.1f}d (< {dias_min}d janela mínima)'

        if last_price > 0 and preco_por is not None:
            queda_pct = ((last_price - preco_por) / last_price) * 100
            if queda_pct < queda_min:
                return True, (
                    f'preço caiu apenas {queda_pct:.1f}% vs último envio '
                    f'(R${last_price:.2f} → R${preco_por:.2f}; mínimo {queda_min}%)'
                )

    # Regra 4: variação do mesmo produto (nome_norm) nos últimos 14 dias
    if nome_norm:
        if conn.execute(
            "SELECT 1 FROM ofertas WHERE nome_norm = ? AND nome_norm != '' "
            "AND coletado_em > datetime('now', '-14 days', 'localtime')",
            (nome_norm,)
        ).fetchone():
            return True, 'variação do mesmo produto coletada nos últimos 14d'

    return False, ''
