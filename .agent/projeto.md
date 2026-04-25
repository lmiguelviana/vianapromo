# Viana Promo — Contexto do Projeto
> ⚠️ OBRIGATÓRIO: Após qualquer alteração, atualize este arquivo E o `docs/sistema.md`.
> Última atualização: 2026-04-25

## Objetivo
Plataforma autônoma de marketing de afiliados fitness. Busca ofertas no Mercado Livre e Magazine Luiza, gera copy de vendas com IA (OpenRouter) ou template fixo, e envia automaticamente para grupos WhatsApp via Evolution API — sem intervenção manual. Portal público em `/` exibe as ofertas enviadas (branding **CasaFit**).

## Tech Stack
- **Frontend:** PHP 8+ com SQLite via PDO, Tailwind CSS CDN, Vanilla JS
- **Bot:** Python 3.9+ — `requests`, `openai` (client OpenRouter), `zoneinfo` (stdlib)
- **Banco:** SQLite (`database/viana.db`) — compartilhado entre PHP e Python
- **APIs:** Evolution API (WhatsApp), Mercado Livre API pública, OpenRouter, Magalu (scraping __NEXT_DATA__)
- **Background (VPS/Docker):** `setsid python3 script.py > /dev/null 2>&1 &` — cria nova sessão, completamente independente do Apache/PHP
- **Background (Windows/XAMPP):** `cmd /C start /B /LOW` — Python roda desacoplado

## Design System
- **Cor primária:** Emerald (`emerald-600`, `emerald-700`)
- **🚫 Proibido:** Roxo, violeta, índigo em qualquer elemento visual
- **Fonte:** Inter (Google Fonts)
- **Componentes:** `.btn-primary`, `.input`, `.label` (via `assets/app.css`)
- **APIs:** sempre retornam `{ "ok": true/false, ... }` via `jsonResponse()`

## Estrutura de Arquivos

```
viana/
├── portal.php          # Portal público CasaFit (sem login) — rota raiz `/`
├── index.php           # Dashboard admin — rota `/v-admin`
├── slides.php          # Admin: gestão de slides do portal
├── links.php           # Links manuais de afiliado
├── grupos.php          # Grupos WhatsApp
├── agenda.php          # Agendamentos de disparo
├── historico.php       # Log de envios
├── fila.php            # Fila de ofertas (Enviar / Adiar / Remover / Rejeitar)
├── config.php          # Configurações (Evolution, ML, Magalu, IA/Template, filtros, banner, logo)
├── usuarios.php        # Gestão de usuários
├── perfil.php          # Perfil (foto, nome, senha)
├── logs.php            # Logs ao vivo (polling 4s, UTF-8 seguro)
├── linktree.php        # Admin: gestão de links do bio (tipo Linktree)
├── bio.php             # Página pública tipo Linktree — rota `/bio`
├── termos.php          # Termos de Uso & Privacidade — rota `/termos`
├── 404.php             # Página 404 personalizada CasaFit
├── login.php / logout.php
│
├── bot/
│   ├── main.py             # Orquestrador (pipeline completo ou steps isolados)
│   ├── coletor.py          # ML API → blacklist → dedup por preço → ofertas (~90 keywords)
│   ├── coletor_magalu.py   # Magalu scraping (__NEXT_DATA__) → dedup por preço (~70 keywords)
│   ├── gerador.py          # IA (OpenRouter) OU template PHP-compatível
│   ├── enriquecedor.py     # Download imagens → /uploads/
│   ├── emissor.py          # Evolution API → historico → status=enviada (pausa configurável)
│   ├── config.py           # get(), set_value(), setup_logging() [_BRTFormatter para BRT]
│   └── requirements.txt
│
├── api/
│   ├── bot_run.php               # Dispara main.py via setsid (VPS) / cmd start /B /LOW (Windows)
│   ├── oferta_enviar.php         # Envio manual: gera template em PHP se mensagem_ia vazia
│   ├── testar_ia.php             # Ping OpenRouter
│   ├── log_tail.php              # Últimas 500 linhas do log em JSON
│   ├── fila.php                  # rejeitar (blacklist) | adiar | remover (sem blacklist) | aprovar
│   ├── fila_limpar.php           # Limpar rejeitadas → salva blacklist ANTES de apagar
│   ├── bio.php                   # CRUD bio_links (criar/editar/toggle/deletar/perfil)
│   ├── upload_logo.php           # Upload logo do sistema (JPG/PNG/WebP/SVG, max 2MB)
│   ├── links.php                 # CRUD links
│   ├── grupos.php                # CRUD grupos
│   ├── grupos_wpp.php            # Lista grupos Evolution API ao vivo
│   ├── agenda.php                # CRUD agendamentos
│   ├── enviar.php                # Disparo manual (link → grupo)
│   ├── upload.php                # Upload de imagem
│   ├── slides.php                # CRUD slides do portal
│   ├── ml_auth.php               # OAuth callback ML
│   ├── ml_refresh.php            # Renova access_token via refresh_token
│   ├── whatsapp_reconectar.php   # Logout + QR code (action=status|logout|qrcode)
│   ├── cron_test.php             # Simula/força execução do cron
│   └── usuarios.php              # CRUD usuários
│
├── app/
│   ├── db.php          # getDB() — busy_timeout ANTES de journal_mode (crítico!)
│   ├── evolution.php   # Classe EvolutionAPI (suporta GET/POST/DELETE)
│   ├── helpers.php     # Layout, sidebar, toast(), jsonResponse(), BASE dinâmico
│   └── auth.php        # requireLogin(), currentUser()
│
├── storage/
│   ├── bot.log         # Log do Python (FileHandler append — sem rotação)
│   └── bot.lock        # Lock anti-execução-dupla
│
├── uploads/            # Imagens (manuais, bot, slides, logos, avatares)
├── assets/app.css      # Design system
└── database/viana.db   # SQLite central
```

## Banco de Dados (SQLite)

### Tabelas
`config` | `links` | `grupos` | `agendamentos` | `historico` | `usuarios` | `ofertas` | `blacklist` | `slides` | `bio_links`

### Tabela `blacklist`
```sql
CREATE TABLE blacklist (
    produto_id_externo TEXT PRIMARY KEY,
    motivo TEXT NOT NULL DEFAULT 'rejeitado',
    criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
)
```
Populada por: rejeição manual (`api/fila.php?action=rejeitar`), `fila_limpar.php`, migração automática do `coletor.py`.

> **Nota:** "Remover" e "Adiar" NÃO adicionam à blacklist — produto pode ser recoletado.

### Tabela `bio_links`
```sql
CREATE TABLE bio_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL DEFAULT '',
    url TEXT NOT NULL DEFAULT '',
    icone TEXT NOT NULL DEFAULT 'link',
    cor TEXT NOT NULL DEFAULT '#059669',
    ordem INTEGER NOT NULL DEFAULT 0,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
)
```

### Tabela `slides`
```sql
CREATE TABLE slides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL DEFAULT '',
    subtitulo TEXT NOT NULL DEFAULT '',
    imagem_path TEXT NOT NULL DEFAULT '',
    link_url TEXT NOT NULL DEFAULT '',
    ordem INTEGER NOT NULL DEFAULT 0,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
)
```

### Chaves importantes em `config`
| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `usar_ia` | `0` | `1`=OpenRouter, `0`=template fixo |
| `mensagem_padrao` | (interno) | Template com `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{EMOJI}` `{LINK}` |
| `bot_desconto_minimo` | `10` | % mínimo de desconto |
| `bot_preco_maximo` | `500` | R$ máximo |
| `bot_intervalo_entre_ofertas` | `0` | Pausa em minutos entre envios |
| `bot_ativo` | `0` | Liga/desliga agendamento automático |
| `bot_intervalo_horas` | `6` | Intervalo entre execuções |
| `portal_banner_ativo` | `1` | Exibe banner hero no topo do portal |
| `portal_banner_titulo` | `''` | Título do banner do portal |
| `portal_banner_subtitulo` | `''` | Subtítulo do banner do portal |
| `magalu_smttag` | `''` | ID de parceiro Magalu (Parceiro Magalu) — gera link de afiliado |
| `magalu_ativo` | `0` | Liga/desliga coleta Magalu |
| `system_logo_url` | `''` | URL pública do logo enviado |
| `system_logo_path` | `''` | Caminho físico do logo no servidor |
| `bio_nome` | `CasaFit Ofertas` | Nome exibido na página /bio |
| `bio_descricao` | `''` | Descrição exibida no /bio |
| `bio_avatar_path` | `''` | Caminho do avatar do /bio |

### Status do Pipeline de Ofertas
```
nova → pronta → enviada
nova → erro_ia
qualquer → adiada         (esconde sem blacklist; pode enviar manualmente depois)
qualquer → rejeitada → [blacklist]
```

**Ações na fila:**
| Ação | Efeito | Pode ser recoletado? |
|------|--------|---------------------|
| Enviar | status=enviada | não (dedup por preço) |
| Adiar | status=adiada | não (dedup por preço; só volta se preço cair) |
| Remover | DELETE da tabela | sim (próximo ciclo do bot) |
| Rejeitar | status=rejeitada + blacklist | nunca |

## Regras Críticas de Implementação

### SQLite Concorrência
```php
// ORDEM OBRIGATÓRIA — busy_timeout ANTES do journal_mode
$pdo->exec('PRAGMA busy_timeout=15000');
$pdo->exec('PRAGMA journal_mode=WAL');
```
```python
# Python também — especialmente em _salvar_tokens (token ML pode ser perdido sem isso)
conn = sqlite3.connect(db_path, timeout=15)
conn.execute('PRAGMA busy_timeout=15000')
conn.execute('PRAGMA journal_mode=WAL')
```

### Deduplicação de Ofertas
```python
# Dedup por produto + preço (NÃO por janela 48h)
# Mesmo produto com mesmo preco_por → ignorado indefinidamente
# Só recoleta se o preço cair
"SELECT 1 FROM ofertas WHERE produto_id_externo = ? AND preco_por = ?"
```

### Token ML — Rotação do refresh_token
```python
# ML rotaciona o refresh_token a cada uso
# _salvar_tokens() usa WAL + busy_timeout + 5 retries com backoff exponencial
# Se falhar, o refresh_token antigo (já invalidado pelo ML) fica no banco
# → bot perde acesso na próxima execução
```

### Magalu — Scraping __NEXT_DATA__
```python
# Magalu é Next.js — produtos ficam embutidos no HTML como JSON
match = re.search(r'<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.*?)</script>', html, re.DOTALL)
# Tenta 4 caminhos: data.products, search.products, products, initialState.search.products
# Link de afiliado: url_produto + "?smttag={ID}&utm_source=parceiro&utm_medium=afiliado"
# Delay 3s entre keywords; retry 3x com backoff 60s/120s/180s em 429
```

### BASE_URL Dinâmico (multi-ambiente)
```php
define('BASE', rtrim(getenv('APP_BASE') !== false ? (string)getenv('APP_BASE') : '/viana', '/'));
```
- `.htaccess` — local (`RewriteBase /viana/`)
- `.htaccess.production` — VPS (`RewriteBase /`), copiado pelo Dockerfile
- **Rota raiz `^/?$` DEVE vir ANTES da condição `-f/-d`** no htaccess

### Logger Python (Windows)
```python
# NÃO usar RotatingFileHandler — tem bug de rename no Windows
fh = logging.FileHandler(LOG_PATH, mode='a', encoding='utf-8')
```

### Background Execution
```php
// VPS/Docker
exec(sprintf('setsid python3 %s > /dev/null 2>&1 &', escapeshellarg($script)));
// Windows/XAMPP
$cmd = sprintf('cmd /C start /B /LOW "" "%s" "%s"', $python, $script);
```

### Retry 429 na ML API
```python
# 3 tentativas com backoff 60s/120s/180s antes de desistir da keyword
# Delay entre keywords: 2s | Delay entre produtos: 0.3s
```

## URLs do Painel
| Rota | Arquivo | Acesso |
|------|---------|--------|
| `/` | `portal.php` | Público |
| `/bio` | `bio.php` | Público (Linktree) |
| `/termos` | `termos.php` | Público |
| `/v-admin` | `index.php` | Admin |
| `/fila` | `fila.php` | Admin |
| `/slides` | `slides.php` | Admin |
| `/linktree` | `linktree.php` | Admin |
| `/links` | `links.php` | Admin |
| `/grupos` | `grupos.php` | Admin |
| `/agenda` | `agenda.php` | Admin |
| `/historico` | `historico.php` | Admin |
| `/logs` | `logs.php` | Admin |
| `/config` | `config.php` | Admin |
| `/perfil` | `perfil.php` | Admin |
| `/usuarios` | `usuarios.php` | Admin |

## Próximos Passos
1. Cadastrar no parceiromagalu.com.br com CPF e inserir smttag no Config → Magalu
2. Métricas de bot no Dashboard (cards de coletadas/enviadas hoje por fonte ML/MGZ)
3. Chatbot de consulta de ofertas via IA no painel
4. Suporte a Amazon/Shopee
