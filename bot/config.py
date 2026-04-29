"""
config.py — Lê as configurações salvas no banco SQLite do Viana Promo.
O bot reutiliza o mesmo banco do painel PHP para não duplicar estado.
"""
import sqlite3
import os
import logging
import datetime
import zoneinfo
from logging.handlers import RotatingFileHandler

_TZ_BRT = zoneinfo.ZoneInfo('America/Sao_Paulo')


class _BRTFormatter(logging.Formatter):
    """Formata timestamps sempre em America/Sao_Paulo, independente do SO."""
    def formatTime(self, record, datefmt=None):
        dt = datetime.datetime.fromtimestamp(record.created, tz=_TZ_BRT)
        return dt.strftime(datefmt or '%Y-%m-%d %H:%M:%S')

# Caminho do banco — relativo à raiz do projeto
DB_PATH  = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
_STORAGE = os.path.join(os.path.dirname(__file__), '..', 'storage')
LOG_PATH = os.path.join(_STORAGE, 'bot.log')

# Log path ativo — pode ser sobrescrito por main.py antes de qualquer setup_logging()
_active_log_path = LOG_PATH


def set_log_path(path: str) -> None:
    """Define o arquivo de log para este processo (deve ser chamado antes de setup_logging)."""
    global _active_log_path
    _active_log_path = path


def setup_logging(name: str) -> logging.Logger:
    """Configura o logger. Usa _active_log_path; se bloqueado, usa só console."""
    logger = logging.getLogger(name)
    if not logger.handlers:
        logger.setLevel(logging.INFO)
        fmt = _BRTFormatter(
            '%(asctime)s [%(levelname)s] [%(name)s] %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )

        # Handler de arquivo — FileHandler simples (RotatingFileHandler tem bug no Windows)
        try:
            os.makedirs(os.path.dirname(_active_log_path), exist_ok=True)
            fh = logging.FileHandler(_active_log_path, mode='a', encoding='utf-8')
            fh.setFormatter(fmt)
            logger.addHandler(fh)
        except PermissionError:
            pass  # outro processo segurando o arquivo — só console

        sh = logging.StreamHandler()
        sh.setFormatter(fmt)
        logger.addHandler(sh)

    return logger


def get(chave: str, default: str = '') -> str:
    """Lê da tabela config; se vazio, tenta variável de ambiente (ML_CLIENT_ID → ml_client_id)."""
    try:
        conn = sqlite3.connect(DB_PATH)
        row = conn.execute(
            'SELECT valor FROM config WHERE chave = ?', (chave,)
        ).fetchone()
        conn.close()
        if row and row[0]:
            return row[0]
    except Exception:
        pass
    # Fallback: env var em UPPER (ml_client_id → ML_CLIENT_ID)
    env_val = os.getenv(chave.upper(), '')
    return env_val if env_val else default


def get_all() -> dict:
    """Retorna todas as configurações como dicionário."""
    try:
        conn = sqlite3.connect(DB_PATH)
        rows = conn.execute('SELECT chave, valor FROM config').fetchall()
        conn.close()
        return {r[0]: r[1] for r in rows}
    except Exception:
        return {}


def set_value(chave: str, valor: str) -> None:
    """Salva um valor na tabela config (upsert)."""
    conn = sqlite3.connect(DB_PATH)
    conn.execute(
        'INSERT OR REPLACE INTO config (chave, valor) VALUES (?, ?)',
        (chave, valor)
    )
    conn.commit()
    conn.close()
