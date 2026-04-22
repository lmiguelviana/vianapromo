"""
enriquecedor.py — Baixa e salva a imagem de cada oferta pronta.

Fluxo:
  1. Busca ofertas com status='pronta' e imagem_path vazio
  2. Faz download da imagem_url
  3. Salva em /uploads/ com nome único
  4. Atualiza imagem_path na oferta
"""
import sqlite3
import requests
import sys
import os
from pathlib import Path

sys.path.insert(0, os.path.dirname(__file__))
import config

log = config.setup_logging('ENRIQUECEDOR')

UPLOADS_DIR = os.path.join(os.path.dirname(__file__), '..', 'uploads')


def baixar_imagem(url: str, oferta_id: int) -> str | None:
    """
    Faz download da imagem e salva em /uploads/.
    Retorna o caminho absoluto do arquivo salvo, ou None se falhar.
    """
    try:
        resp = requests.get(url, timeout=20, stream=True)
        resp.raise_for_status()

        # Determina extensão pela Content-Type
        ct = resp.headers.get('Content-Type', 'image/jpeg')
        ext = 'jpg'
        if 'png' in ct:
            ext = 'png'
        elif 'webp' in ct:
            ext = 'webp'

        nome_arquivo = f'oferta_{oferta_id}.{ext}'
        caminho = os.path.join(UPLOADS_DIR, nome_arquivo)

        Path(UPLOADS_DIR).mkdir(parents=True, exist_ok=True)

        with open(caminho, 'wb') as f:
            for chunk in resp.iter_content(1024 * 64):
                f.write(chunk)

        log.info(f'  ✅ Imagem salva: {nome_arquivo} ({os.path.getsize(caminho) // 1024} KB)')
        return caminho

    except Exception as e:
        log.warning(f'  ⚠️ Falha ao baixar imagem da oferta {oferta_id}: {e}')
        return None


def enriquecer() -> int:
    """Baixa imagens de todas as ofertas prontas sem imagem local. Retorna o total processado."""
    db_path = os.path.join(os.path.dirname(__file__), '..', 'database', 'viana.db')
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    conn.execute('PRAGMA journal_mode=WAL')

    ofertas = conn.execute(
        """SELECT id, imagem_url FROM ofertas
           WHERE status = 'pronta' AND imagem_path = '' AND imagem_url != ''"""
    ).fetchall()

    if not ofertas:
        log.info('Nenhuma imagem para baixar.')
        conn.close()
        return 0

    log.info(f'🖼  Baixando imagens de {len(ofertas)} oferta(s)')
    processadas = 0

    for oferta in ofertas:
        caminho = baixar_imagem(oferta['imagem_url'], oferta['id'])
        if caminho:
            conn.execute(
                'UPDATE ofertas SET imagem_path = ? WHERE id = ?',
                (caminho, oferta['id'])
            )
            processadas += 1

    conn.commit()
    conn.close()
    log.info(f'✔ {processadas}/{len(ofertas)} imagens baixadas')
    return processadas


if __name__ == '__main__':
    enriquecer()
