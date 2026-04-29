"""
emissor.py — Envia as ofertas prontas para os grupos WhatsApp via Evolution API.

Fluxo:
  1. Verifica se WhatsApp está conectado (aborta se não)
  2. Busca ofertas com status='pronta' (texto gerado)
  3. Aplica limite bot_max_envios_por_ciclo se configurado
  4. Para cada oferta, envia para cada grupo com intervalo de segurança
  5. Só marca 'enviada' se pelo menos 1 grupo recebeu com sucesso
  6. Registra no histórico
"""
import sqlite3
import requests
import base64
import sys
import os
import time

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('EMISSOR')

INTERVALO_GRUPO_SEGUNDOS = 5

# Lock exclusivo do emissor — impede que bot ML e bot Shopee enviem ao mesmo tempo
EMISSOR_LOCK = os.path.join(os.path.dirname(__file__), '..', 'storage', 'emissor.lock')


def _adquirir_lock_emissor() -> bool:
    """Tenta adquirir o lock. Retorna False se outro emissor já está rodando."""
    os.makedirs(os.path.dirname(EMISSOR_LOCK), exist_ok=True)
    try:
        fd = os.open(EMISSOR_LOCK, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        with os.fdopen(fd, 'w') as f:
            f.write(str(os.getpid()))
        return True
    except FileExistsError:
        try:
            pid = int(open(EMISSOR_LOCK).read().strip() or '0')
        except (ValueError, OSError):
            pid = 0
        try:
            if pid > 0:
                os.kill(pid, 0)
                log.warning(f'Emissor já está rodando (PID {pid}). Bot vai aguardar próximo ciclo.')
                return False
        except (OSError, ProcessLookupError):
            pass
        try: os.remove(EMISSOR_LOCK)
        except FileNotFoundError: pass
        return _adquirir_lock_emissor()


def _remover_lock_emissor():
    try:
        pid = int(open(EMISSOR_LOCK).read().strip() or '0')
        if pid == os.getpid():
            os.remove(EMISSOR_LOCK)
    except (FileNotFoundError, ValueError, OSError):
        pass


def get_evolution_headers() -> dict:
    return {
        'Content-Type': 'application/json',
        'apikey': config.get('evolution_apikey'),
    }


def whatsapp_conectado(base_url: str, instance: str) -> tuple[bool, str]:
    """Checa se a instância WhatsApp está conectada. Retorna (conectado, estado)."""
    try:
        r = requests.get(
            f'{base_url}/instance/connectionState/{instance}',
            headers=get_evolution_headers(), timeout=10,
        )
        if r.status_code != 200:
            return False, f'HTTP {r.status_code}'
        data  = r.json()
        estado = data.get('instance', {}).get('state') or data.get('state') or 'unknown'
        return estado == 'open', estado
    except Exception as e:
        return False, f'erro: {e}'


def send_text(base_url: str, instance: str, jid: str, texto: str) -> dict:
    url = f'{base_url}/message/sendText/{instance}'
    payload = {'number': jid, 'text': texto}
    resp = requests.post(url, json=payload, headers=get_evolution_headers(), timeout=20)
    return resp.json()


def send_media(base_url: str, instance: str, jid: str,
               caption: str, media_source: str, is_url: bool) -> dict:
    url = f'{base_url}/message/sendMedia/{instance}'

    if is_url:
        media_value = media_source
    else:
        with open(media_source, 'rb') as f:
            media_value = base64.b64encode(f.read()).decode()

    payload = {
        'number':    jid,
        'mediatype': 'image',
        'mimetype':  'image/jpeg',
        'caption':   caption,
        'media':     media_value,
        'fileName':  'oferta.jpg',
    }
    resp = requests.post(url, json=payload, headers=get_evolution_headers(), timeout=30)
    return resp.json()


def registrar_historico(conn: sqlite3.Connection, oferta_id: int,
                        grupo_id: int, status: str, erro: str | None = None) -> None:
    conn.execute(
        """INSERT INTO historico (link_id, grupo_id, status, mensagem_erro)
           VALUES (NULL, ?, ?, ?)""",
        (grupo_id, status, erro)
    )


def montar_texto_final(oferta: dict) -> str:
    """Substitui {LINK} pelo tracker (contabiliza cliques) ou link direto."""
    site_url = config.get('site_url', '').rstrip('/')
    if site_url:
        link = f"{site_url}/api/click.php?id={oferta['id']}"
    else:
        link = oferta['url_afiliado']
    return oferta['mensagem_ia'].replace('{LINK}', link)


def _where_fonte(fonte: str | None) -> str:
    if fonte == 'ml':
        return " AND fonte IN ('ML', 'MGZ')"
    if fonte == 'shopee':
        return " AND fonte = 'SHP'"
    return ""


def enviar(fonte: str | None = None) -> int:
    """Envia ofertas prontas da fonte informada. Retorna o número de envios com sucesso."""
    if not _adquirir_lock_emissor():
        return 0

    import atexit
    atexit.register(_remover_lock_emissor)

    base_url = config.get('evolution_url', '').rstrip('/')
    instance = config.get('evolution_instance', '')

    if not base_url or not instance:
        log.error('Evolution API não configurada. Configure em /viana/config')
        return 0

    # Pré-check: WhatsApp conectado antes de abrir o banco
    conectado, estado = whatsapp_conectado(base_url, instance)
    if not conectado:
        log.error(f'❌ WhatsApp desconectado (estado: {estado}). Abortando envio. Reconecte em Config.')
        return 0

    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path, timeout=10)
    conn.row_factory = sqlite3.Row
    conn.execute('PRAGMA busy_timeout=10000')
    conn.execute('PRAGMA journal_mode=WAL')

    filtro_fonte = _where_fonte(fonte)
    ofertas = conn.execute(
        f"""SELECT * FROM ofertas
           WHERE status = 'pronta' AND mensagem_ia != ''{filtro_fonte}
           ORDER BY desconto_pct DESC"""
    ).fetchall()

    grupos = conn.execute("SELECT * FROM grupos WHERE ativo = 1").fetchall()

    if not ofertas:
        log.info('Nenhuma oferta pronta para enviar.')
        conn.close()
        return 0

    if not grupos:
        log.warning('Nenhum grupo ativo configurado.')
        conn.close()
        return 0

    intervalo_ofertas    = int(config.get('bot_intervalo_entre_ofertas') or 0)
    max_envios_por_ciclo = int(config.get('bot_max_envios_por_ciclo') or 0)

    if max_envios_por_ciclo and len(ofertas) > max_envios_por_ciclo:
        log.info(f'   Limitando a {max_envios_por_ciclo} oferta(s) (de {len(ofertas)} prontas)')
        ofertas = ofertas[:max_envios_por_ciclo]

    log.info(f'🚀 Enviando {len(ofertas)} oferta(s) para {len(grupos)} grupo(s)'
             + (f' — intervalo: {intervalo_ofertas}min' if intervalo_ofertas else ''))
    enviados_total = 0

    for i, oferta in enumerate(ofertas):
        oferta_dict   = dict(oferta)
        texto         = montar_texto_final(oferta_dict)
        nome_curto    = oferta_dict['nome'][:50]
        sucesso_oferta = 0

        for grupo in grupos:
            jid      = grupo['group_jid']
            grupo_id = grupo['id']
            log.info(f'  → Enviando "{nome_curto}" para {grupo["nome"]}')

            try:
                if oferta_dict['imagem_path'] and os.path.exists(oferta_dict['imagem_path']):
                    result = send_media(base_url, instance, jid, texto,
                                        oferta_dict['imagem_path'], is_url=False)
                elif oferta_dict['imagem_url']:
                    result = send_media(base_url, instance, jid, texto,
                                        oferta_dict['imagem_url'], is_url=True)
                else:
                    result = send_text(base_url, instance, jid, texto)

                if result.get('key') or result.get('status') == 'PENDING':
                    registrar_historico(conn, oferta_dict['id'], grupo_id, 'sucesso')
                    sucesso_oferta += 1
                    enviados_total += 1
                    log.info('  ✅ Enviado')
                else:
                    erro_msg = str(result.get('message', result))[:200]
                    registrar_historico(conn, oferta_dict['id'], grupo_id, 'erro', erro_msg)
                    log.warning(f'  ⚠️  Falha: {erro_msg}')

                    # Se erro de conexão WhatsApp, aborta tudo
                    if 'Connection Closed' in erro_msg or 'instance' in erro_msg.lower():
                        log.error('❌ WhatsApp desconectou durante envio. Abortando.')
                        conn.commit()
                        conn.close()
                        return enviados_total

            except Exception as e:
                registrar_historico(conn, oferta_dict['id'], grupo_id, 'erro', str(e))
                log.error(f'  ❌ Exceção: {e}')

            time.sleep(INTERVALO_GRUPO_SEGUNDOS)

        # Só marca 'enviada' se pelo menos 1 grupo recebeu com sucesso
        if sucesso_oferta > 0:
            conn.execute(
                "UPDATE ofertas SET status = 'enviada', enviado_em = datetime('now','localtime') WHERE id = ?",
                (oferta_dict['id'],)
            )
            log.info(f'  📦 Marcada como enviada ({sucesso_oferta}/{len(grupos)} grupos OK)')
        else:
            log.warning('  ⚠️  Nenhum grupo recebeu — oferta continua em "pronta" para retry')
        conn.commit()

        if intervalo_ofertas > 0 and i < len(ofertas) - 1:
            log.info(f'  ⏱ Aguardando {intervalo_ofertas} min...')
            time.sleep(intervalo_ofertas * 60)

    conn.close()
    log.info(f'✔ {enviados_total} envios com sucesso')
    return enviados_total


if __name__ == '__main__':
    enviar()
