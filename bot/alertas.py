"""
alertas.py — Grava alertas do bot na tabela bot_alertas do SQLite.

Uso:
    from alertas import gravar_alerta
    gravar_alerta('erro', 'coletor_ml', 'Token ML expirado!')
"""
import sqlite3
import os
import time


_DB_PATH = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')


def gravar_alerta(tipo: str, fonte: str, mensagem: str) -> None:
    """
    Grava um alerta na tabela bot_alertas.

    Args:
        tipo:     'erro' | 'aviso' | 'info'
        fonte:    nome do módulo (ex: 'coletor_ml', 'emissor', 'main')
        mensagem: texto descritivo do problema
    """
    for tentativa in range(3):
        try:
            conn = sqlite3.connect(_DB_PATH, timeout=10)
            conn.execute('PRAGMA busy_timeout=10000')
            conn.execute('PRAGMA journal_mode=WAL')
            conn.execute(
                "INSERT INTO bot_alertas (tipo, fonte, mensagem) VALUES (?, ?, ?)",
                (tipo, fonte, mensagem[:500])
            )
            conn.commit()
            conn.close()
            return
        except sqlite3.OperationalError:
            time.sleep(1 * (tentativa + 1))
        except Exception:
            return


def gravar_erro(fonte: str, mensagem: str) -> None:
    """Atalho para alertas de tipo 'erro'."""
    gravar_alerta('erro', fonte, mensagem)


def gravar_aviso(fonte: str, mensagem: str) -> None:
    """Atalho para alertas de tipo 'aviso'."""
    gravar_alerta('aviso', fonte, mensagem)
