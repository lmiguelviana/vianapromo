"""
main.py — Orquestrador principal do bot Viana Promo.

Executa o pipeline completo na ordem:
  coletor → gerador → enriquecedor → emissor

Pode ser chamado diretamente ou via agendador.
Uso:
  python main.py              # pipeline completo
  python main.py --coletar    # só coleta
  python main.py --gerar      # só gera textos
  python main.py --enviar     # só envia
"""
import sys
import os
import atexit

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('MAIN')

# ── Lock file com PID real ────────────────────────────────────────────────────
LOCK_PATH = os.path.join(os.path.dirname(__file__), '..', 'storage', 'bot.lock')

def _gravar_lock():
    """Grava o PID real no lock file."""
    os.makedirs(os.path.dirname(LOCK_PATH), exist_ok=True)
    with open(LOCK_PATH, 'w') as f:
        f.write(str(os.getpid()))

def _remover_lock():
    """Remove o lock file ao sair (normal ou por exceção)."""
    try:
        os.remove(LOCK_PATH)
    except FileNotFoundError:
        pass

_gravar_lock()
atexit.register(_remover_lock)  # garante remoção mesmo em crash


def verificar_config() -> bool:
    """Verifica se as configurações mínimas estão presentes."""
    problemas = []
    if not config.get('evolution_url'):
        problemas.append('evolution_url não configurada')
    if not config.get('evolution_apikey'):
        problemas.append('evolution_apikey não configurada')
    if not config.get('evolution_instance'):
        problemas.append('evolution_instance não configurada')
    # openrouter_apikey é opcional — sistema suporta modo template (usar_ia=0)

    if problemas:
        for p in problemas:
            log.warning(f'⚠️  Config ausente: {p}')
        return False
    return True


def pipeline_completo():
    log.info('=' * 60)
    log.info('🤖 VIANA PROMO BOT — iniciando pipeline')
    log.info('=' * 60)

    if not verificar_config():
        log.error('Configure as chaves em http://localhost/viana/config antes de rodar o bot.')
        return

    import coletor
    import coletor_magalu
    import gerador
    import enriquecedor
    import emissor

    novas_ml     = coletor.coletar()
    novas_mgz    = coletor_magalu.coletar()
    geradas      = gerador.gerar_todas()
    imagens      = enriquecedor.enriquecer()
    enviados     = emissor.enviar()

    log.info('=' * 60)
    log.info(f'📊 RESUMO: ML={novas_ml} | MGZ={novas_mgz} | textos={geradas} | imagens={imagens} | enviadas={enviados}')
    log.info('=' * 60)


if __name__ == '__main__':
    args = sys.argv[1:]

    if '--coletar' in args:
        import coletor; coletor.coletar()
    elif '--gerar' in args:
        import gerador; gerador.gerar_todas()
    elif '--enriquecer' in args:
        import enriquecedor; enriquecedor.enriquecer()
    elif '--enviar' in args:
        import emissor; emissor.enviar()
    else:
        pipeline_completo()
