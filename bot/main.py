"""
main.py — Orquestrador principal do bot Viana Promo.

Executa o pipeline completo na ordem:
  coletor (ML) → coletor_magalu → coletor_shopee → gerador → enriquecedor → emissor

Pode ser chamado diretamente ou via agendador.
Uso:
  python main.py                    # pipeline completo
  python main.py --coletar          # só coleta (ML + Magalu + Shopee)
  python main.py --coletar-shopee   # só Shopee
  python main.py --gerar            # só gera textos
  python main.py --enviar           # só envia
"""
import sys
import os
import atexit

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('MAIN')

# ── Lock file com PID real ────────────────────────────────────────────────────
LOCK_PATH = os.path.join(os.path.dirname(__file__), '..', 'storage', 'bot.lock')


def _pid_vivo(pid: int) -> bool:
    if pid <= 0:
        return False
    try:
        os.kill(pid, 0)
    except (OSError, ProcessLookupError):
        return False

    # No Linux, confirma que o PID pertence ao nosso bot (não é PID reaproveitado do kernel)
    cmdline_path = f'/proc/{pid}/cmdline'
    try:
        if os.path.exists(cmdline_path):
            with open(cmdline_path, 'rb') as f:
                cmdline = f.read().replace(b'\x00', b' ').decode('utf-8', errors='replace')
            return 'main.py' in cmdline or ('python' in cmdline.lower() and 'viana' in cmdline.lower())
    except OSError:
        pass
    return True  # fallback conservador


def _adquirir_lock() -> None:
    """Cria lock atomicamente; se já existe com PID vivo, aborta."""
    os.makedirs(os.path.dirname(LOCK_PATH), exist_ok=True)
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
                log.warning(f'Bot já está rodando (PID {pid_antigo}). Abortando.')
                sys.exit(0)

            log.info(f'Lock zumbi removido (PID {pid_antigo} não está mais vivo)')
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
    if not config.get('evolution_url'):
        problemas.append('evolution_url não configurada')
    if not config.get('evolution_apikey'):
        problemas.append('evolution_apikey não configurada')
    if not config.get('evolution_instance'):
        problemas.append('evolution_instance não configurada')

    if problemas:
        for p in problemas:
            log.warning(f'⚠️  Config ausente: {p}')
        return False
    return True


def pipeline_completo():
    log.info('=' * 60)
    log.info('🤖 VIANA PROMO BOT — iniciando pipeline')
    log.info('=' * 60)

    # Verifica flag bot_ativo — permite pausar/retomar sem editar cron
    if config.get('bot_ativo', '1') == '0':
        log.info('⏸ Bot pausado (bot_ativo=0). Ative em Config para retomar.')
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

    if '--coletar' in args:
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
