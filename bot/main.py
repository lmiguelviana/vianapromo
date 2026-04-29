"""
main.py — Orquestrador principal do bot Viana Promo.

Dois bots independentes com locks separados:
  --fonte ml      → pipeline ML + Magalu (lock: bot_ml.lock)
  --fonte shopee  → pipeline Shopee     (lock: bot_shopee.lock)
  (sem --fonte)   → pipeline completo   (lock: bot.lock)

Outros args isolados:
  --coletar       → só coleta ML + Magalu + Shopee
  --gerar         → só gera textos
  --enriquecer    → só baixa imagens
  --enviar        → só envia
"""
import sys
import os
import atexit

sys.path.insert(0, os.path.dirname(__file__))
import config

STORAGE = os.path.join(os.path.dirname(__file__), '..', 'storage')

# ── Determina fonte e lock ANTES de importar qualquer módulo pesado ───────────
_args = sys.argv[1:]
_fonte = None
for a in _args:
    if a.startswith('--fonte='):
        _fonte = a.split('=', 1)[1].lower()
    elif a == '--fonte' and _args.index(a) + 1 < len(_args):
        _fonte = _args[_args.index(a) + 1].lower()

if _fonte == 'ml':
    LOCK_PATH = os.path.join(STORAGE, 'bot_ml.lock')
    _log_nome = 'MAIN-ML'
    config.set_log_path(os.path.join(STORAGE, 'bot_ml.log'))
    config.set_fonte('ml')
elif _fonte == 'shopee':
    LOCK_PATH = os.path.join(STORAGE, 'bot_shopee.lock')
    _log_nome = 'MAIN-SHP'
    config.set_log_path(os.path.join(STORAGE, 'bot_shopee.log'))
    config.set_fonte('shopee')
else:
    LOCK_PATH = os.path.join(STORAGE, 'bot.lock')
    _log_nome = 'MAIN'
    # log path padrão (bot.log) e fonte genérica já definidos em config

log = config.setup_logging(_log_nome)


def _bot_pausado(fonte: str | None = None) -> bool:
    """Pausa por fonte. Bot ML e Shopee não dependem mais do bot_ativo legado."""
    if fonte:
        especifico = config._get_raw(f'bot_{fonte}_ativo', '1')
        if especifico == '0':
            log.info(f'⏸ Bot {fonte} pausado (bot_{fonte}_ativo=0).')
            return True
        return False
    legado = config._get_raw('bot_ativo', '1')
    if legado == '0':
        log.info('⏸ Pipeline completo legado pausado (bot_ativo=0).')
        return True
    return False


def _pid_vivo(pid: int) -> bool:
    if pid <= 0:
        return False
    try:
        os.kill(pid, 0)
    except (OSError, ProcessLookupError):
        return False
    # Linux: verifica se o PID pertence ao nosso bot, não a um processo do kernel
    cmdline_path = f'/proc/{pid}/cmdline'
    try:
        if os.path.exists(cmdline_path):
            with open(cmdline_path, 'rb') as f:
                cmdline = f.read().replace(b'\x00', b' ').decode('utf-8', errors='replace')
            if 'main.py' not in cmdline:
                return False
            if _fonte:
                return f'--fonte {_fonte}' in cmdline or f'--fonte={_fonte}' in cmdline
            return True
    except OSError:
        return False
    return True  # Windows/macOS fallback


def _adquirir_lock() -> None:
    os.makedirs(STORAGE, exist_ok=True)
    while True:
        try:
            fd = os.open(LOCK_PATH, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
            with os.fdopen(fd, 'w') as f:
                f.write(str(os.getpid()))
            return
        except FileExistsError:
            try:
                with open(LOCK_PATH) as f:
                    pid_antigo = int((f.read() or '0').strip())
            except (ValueError, OSError):
                pid_antigo = 0
            if pid_antigo > 0 and _pid_vivo(pid_antigo):
                log.warning(f'Bot ({_log_nome}) já está rodando (PID {pid_antigo}). Abortando.')
                sys.exit(0)
            log.info(f'Lock zumbi removido (PID {pid_antigo})')
            try:
                os.remove(LOCK_PATH)
            except FileNotFoundError:
                pass


def _remover_lock():
    try:
        with open(LOCK_PATH) as f:
            pid_no_arquivo = int((f.read() or '0').strip())
        if pid_no_arquivo == os.getpid():
            os.remove(LOCK_PATH)
    except (FileNotFoundError, ValueError, OSError):
        pass


_adquirir_lock()
atexit.register(_remover_lock)


def verificar_config() -> bool:
    problemas = []
    if not config.get('evolution_url'):     problemas.append('evolution_url não configurada')
    if not config.get('evolution_apikey'):  problemas.append('evolution_apikey não configurada')
    if not config.get('evolution_instance'):problemas.append('evolution_instance não configurada')
    if problemas:
        for p in problemas: log.warning(f'⚠️  Config ausente: {p}')
        return False
    return True


def pipeline_ml():
    """Bot ML: coleta ML + Magalu → gera → enriquece → envia."""
    log.info('=' * 60)
    log.info('🤖 BOT ML — Mercado Livre + Magazine Luiza')
    log.info('=' * 60)

    if _bot_pausado('ml'):
        return

    if not verificar_config():
        log.error('Configure as chaves em Config antes de rodar o bot.')
        return

    import coletor
    import coletor_magalu
    import gerador
    import enriquecedor
    import emissor

    novas_ml  = coletor.coletar()
    novas_mgz = coletor_magalu.coletar()
    geradas   = gerador.gerar_todas('ml')
    imagens   = enriquecedor.enriquecer('ml')
    enviados  = emissor.enviar('ml')

    log.info('=' * 60)
    log.info(f'📊 RESUMO ML: ML={novas_ml} | MGZ={novas_mgz} | textos={geradas} | imagens={imagens} | enviadas={enviados}')
    log.info('=' * 60)


def pipeline_shopee():
    """Bot Shopee: coleta Shopee → gera → enriquece → envia."""
    log.info('=' * 60)
    log.info('🛒 BOT SHOPEE')
    log.info('=' * 60)

    if _bot_pausado('shopee'):
        return

    if not verificar_config():
        log.error('Configure as chaves em Config antes de rodar o bot.')
        return

    import coletor_shopee
    import gerador
    import enriquecedor
    import emissor

    novas_shp = coletor_shopee.coletar()
    geradas   = gerador.gerar_todas('shopee')
    imagens   = enriquecedor.enriquecer('shopee')
    enviados  = emissor.enviar('shopee')

    log.info('=' * 60)
    log.info(f'📊 RESUMO SHP: SHP={novas_shp} | textos={geradas} | imagens={imagens} | enviadas={enviados}')
    log.info('=' * 60)


def pipeline_completo():
    """Pipeline completo: ML + Magalu + Shopee → gera → enriquece → envia."""
    log.info('=' * 60)
    log.info('🤖 VIANA PROMO BOT — pipeline completo')
    log.info('=' * 60)

    if _bot_pausado(None):
        return

    if not verificar_config():
        log.error('Configure as chaves em Config antes de rodar o bot.')
        return

    import coletor
    import coletor_magalu
    import coletor_shopee
    import gerador
    import enriquecedor
    import emissor

    novas_ml  = coletor.coletar()
    novas_mgz = coletor_magalu.coletar()
    novas_shp = coletor_shopee.coletar()
    geradas   = gerador.gerar_todas()
    imagens   = enriquecedor.enriquecer()
    enviados  = emissor.enviar()

    log.info('=' * 60)
    log.info(f'📊 RESUMO: ML={novas_ml} | MGZ={novas_mgz} | SHP={novas_shp} | textos={geradas} | imagens={imagens} | enviadas={enviados}')
    log.info('=' * 60)


if __name__ == '__main__':
    args = sys.argv[1:]

    # Modo fonte isolado
    if _fonte == 'ml':
        pipeline_ml()
    elif _fonte == 'shopee':
        pipeline_shopee()
    # Steps avulsos (sem lock de fonte)
    elif '--coletar' in args:
        import coletor; coletor.coletar()
        import coletor_magalu; coletor_magalu.coletar()
        import coletor_shopee; coletor_shopee.coletar()
    elif '--coletar-shopee' in args:
        import coletor_shopee; coletor_shopee.coletar()
    elif '--gerar' in args:
        import gerador; gerador.gerar_todas()
    elif '--enriquecer' in args:
        import enriquecedor; enriquecedor.enriquecer()
    elif '--enviar' in args:
        import emissor; emissor.enviar()
    else:
        pipeline_completo()
