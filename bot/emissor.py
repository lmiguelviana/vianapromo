"""
emissor.py — Envia as ofertas prontas para os grupos WhatsApp via Evolution API.

Fluxo:
  1. Busca ofertas com status='pronta' (texto gerado + imagem baixada)
  2. Busca todos os grupos ativos no banco
  3. Para cada oferta, envia para cada grupo com intervalo de segurança
  4. Atualiza status para 'enviada' ou 'erro'
  5. Registra no histórico
"""
import sqlite3
import requests
import base64
import sys
import os
import time
from datetime import datetime

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('EMISSOR')

# Intervalo fixo entre mensagens para o mesmo grupo (anti-bloqueio WhatsApp)
INTERVALO_GRUPO_SEGUNDOS = 5


def get_evolution_headers() -> dict:
    return {
        'Content-Type': 'application/json',
        'apikey': config.get('evolution_apikey'),
    }


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
    """Substitui {LINK} pelo tracker (contabiliza cliques) ou link direto se site_url não configurado."""
    site_url = config.get('site_url', '').rstrip('/')
    if site_url:
        link = f"{site_url}/api/click.php?id={oferta['id']}"
    else:
        link = oferta['url_afiliado']
    return oferta['mensagem_ia'].replace('{LINK}', link)


def enviar() -> int:
    """Envia todas as ofertas prontas. Retorna o número de envios com sucesso."""
    base_url = config.get('evolution_url', '').rstrip('/')
    instance = config.get('evolution_instance', '')

    if not base_url or not instance:
        log.error('Evolution API não configurada. Configure em /viana/config')
        return 0

    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path, timeout=10)
    conn.row_factory = sqlite3.Row
    conn.execute('PRAGMA busy_timeout=10000')
    conn.execute('PRAGMA journal_mode=WAL')

    # Busca ofertas prontas com texto gerado
    ofertas = conn.execute(
        """SELECT * FROM ofertas
           WHERE status = 'pronta' AND mensagem_ia != ''
           ORDER BY desconto_pct DESC"""
    ).fetchall()

    # Busca grupos ativos
    grupos = conn.execute(
        "SELECT * FROM grupos WHERE ativo = 1"
    ).fetchall()

    if not ofertas:
        log.info('Nenhuma oferta pronta para enviar.')
        conn.close()
        return 0

    if not grupos:
        log.warning('Nenhum grupo ativo configurado.')
        conn.close()
        return 0

    intervalo_ofertas = int(config.get('bot_intervalo_entre_ofertas') or 0)

    log.info(f'🚀 Enviando {len(ofertas)} oferta(s) para {len(grupos)} grupo(s)'
             + (f' — intervalo entre ofertas: {intervalo_ofertas}min' if intervalo_ofertas else ''))
    enviados = 0

    for i, oferta in enumerate(ofertas):
        oferta_dict = dict(oferta)
        texto = montar_texto_final(oferta_dict)
        nome_curto = oferta_dict['nome'][:50]

        for grupo in grupos:
            jid = grupo['group_jid']
            grupo_id = grupo['id']
            log.info(f'  → Enviando "{nome_curto}" para {grupo["nome"]}')

            try:
                # Prioridade: arquivo local > URL externa > texto puro
                if oferta_dict['imagem_path'] and os.path.exists(oferta_dict['imagem_path']):
                    result = send_media(base_url, instance, jid, texto,
                                        oferta_dict['imagem_path'], is_url=False)
                elif oferta_dict['imagem_url']:
                    result = send_media(base_url, instance, jid, texto,
                                        oferta_dict['imagem_url'], is_url=True)
                else:
                    result = send_text(base_url, instance, jid, texto)

                # A Evolution API retorna 'key' no body quando enviou com sucesso
                if result.get('key') or result.get('status') == 'PENDING':
                    registrar_historico(conn, oferta_dict['id'], grupo_id, 'sucesso')
                    enviados += 1
                    log.info('  ✅ Enviado')
                else:
                    erro_msg = str(result.get('message', result))[:200]
                    registrar_historico(conn, oferta_dict['id'], grupo_id, 'erro', erro_msg)
                    log.warning(f'  ⚠️  Falha: {erro_msg}')

            except Exception as e:
                registrar_historico(conn, oferta_dict['id'], grupo_id, 'erro', str(e))
                log.error(f'  ❌ Exceção: {e}')

            time.sleep(INTERVALO_GRUPO_SEGUNDOS)

        # Atualiza status da oferta independentemente do grupo
        conn.execute(
            "UPDATE ofertas SET status = 'enviada', enviado_em = datetime('now','localtime') WHERE id = ?",
            (oferta_dict['id'],)
        )
        conn.commit()

        # Pausa entre ofertas (exceto após a última)
        if intervalo_ofertas > 0 and i < len(ofertas) - 1:
            log.info(f'  ⏱ Aguardando {intervalo_ofertas} min antes da próxima oferta...')
            time.sleep(intervalo_ofertas * 60)

    conn.close()
    log.info(f'✔ {enviados} envios com sucesso')
    return enviados


if __name__ == '__main__':
    enviar()
