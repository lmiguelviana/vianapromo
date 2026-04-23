# Viana Promo — Contexto do Projeto
> ⚠️ OBRIGATÓRIO: Após qualquer alteração, atualize este arquivo E o `docs/sistema.md`.
> Última atualização: 2026-04-23

## Objetivo
Plataforma autônoma de marketing de afiliados fitness. Busca ofertas no Mercado Livre, gera copy de vendas com IA (OpenRouter) ou template fixo, e envia automaticamente para grupos WhatsApp via Evolution API — sem intervenção manual. Portal público em `/` exibe as ofertas enviadas.

## Tech Stack
- **Frontend:** PHP 8+ com SQLite via PDO, Tailwind CSS CDN, Vanilla JS
- **Bot:** Python 3.9+ — `requests`, `openai` (client OpenRouter), `zoneinfo` (stdlib)
- **Banco:** SQLite (`database/viana.db`) — compartilhado entre PHP e Python
- **APIs:** Evolution API (WhatsApp), Mercado Livre API pública, OpenRouter
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
├── portal.php          # Portal público (sem login) — rota raiz `/`
├── index.php           # Dashboard admin — rota `/v-admin`
├── slides.php          # Admin: gestão de slides do portal
├── links.php           # Links manuais de afiliado
├── grupos.php          # Grupos WhatsApp
├── agenda.php          # Agendamentos de disparo
├── historico.php       # Log de envios
├── fila.php            # Fila de ofertas (Enviar / Rejeitar / Limpar)
├── config.php          # Configurações (Evolution, ML, IA/Template, filtros, banner portal)
├── usuarios.php        # Gestão de usuários
├── perfil.php          # Perfil (foto, nome, senha)
├── logs.php            # Logs ao vivo (polling 4s, UTF-8 seguro)
├── login.php / logout.php
│
├── bot/
│   ├── main.py         # Orquestrador (pipeline completo ou steps isolados)
│   ├── coletor.py      # ML API → blacklist check → 48h dedup → ofertas (retry backoff 429)
│   ├── gerador.py      # IA (OpenRouter) OU template PHP-compatível
│   ├── enriquecedor.py # Download imagens → /uploads/
│   ├── emissor.py      # Evolution API → historico → status=enviada (pausa configurável)
│   ├── config.py       # get(), set_value(), setup_logging() [FileHandler; _BRTFormatter para BRT]
│   └── requirements.txt
│
├── api/
│   ├── bot_run.php       # Dispara main.py via setsid (VPS) / cmd start /B /LOW (Windows)
│   ├── oferta_enviar.php # Envio manual: gera template em PHP se mensagem_ia vazia
│   ├── testar_ia.php     # Ping OpenRouter — verifica API Key + modelo
│   ├── log_tail.php      # Últimas 500 linhas do log em JSON (polling do logs.php)
│   ├── fila.php          # Rejeitar → insere na blacklist
│   ├── fila_limpar.php   # Limpar rejeitadas → salva blacklist ANTES de apagar
│   ├── links.php         # CRUD links
│   ├── grupos.php        # CRUD grupos
│   ├── grupos_wpp.php    # Lista grupos Evolution API ao vivo
│   ├── agenda.php        # CRUD agendamentos
│   ├── enviar.php        # Disparo manual (link → grupo)
│   ├── upload.php        # Upload de imagem
│   ├── slides.php        # CRUD slides do portal (criar/editar/toggle/deletar)
│   ├── ml_auth.php           # OAuth callback ML
│   ├── ml_refresh.php        # Renova access_token via refresh_token (sem novo login)
│   ├── whatsapp_reconectar.php # Logout + QR code para reconectar número (action=status|logout|qrcode)
│   └── usuarios.php          # CRUD usuários
│
├── app/
│   ├── db.php          # getDB() — busy_timeout ANTES de journal_mode (crítico!)
│   ├── evolution.php   # Classe EvolutionAPI (suporta GET/POST/DELETE)
│   ├── helpers.php     # Layout, sidebar, toast(), jsonResponse()
│   └── auth.php        # requireLogin(), currentUser()
│
├── storage/
│   ├── bot.log         # Log do Python (FileHandler append — sem rotação)
│   └── bot.lock        # Lock anti-execução-dupla
│
├── uploads/            # Imagens (manuais, bot e slides)
├── assets/app.css      # Design system
└── database/viana.db   # SQLite central
```

## Banco de Dados (SQLite)

### Tabelas
`config` | `links` | `grupos` | `agendamentos` | `historico` | `usuarios` | `ofertas` | `blacklist` | `slides`

### Tabela `blacklist`
```sql
CREATE TABLE blacklist (
    produto_id_externo TEXT PRIMARY KEY,
    motivo TEXT NOT NULL DEFAULT 'rejeitado',
    criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
)
```
Populada por: rejeição manual, `fila_limpar.php`, migração automática do `coletor.py`.

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
Gerenciada por `/slides` (admin) e `api/slides.php`. Exibida no slider do portal público.

### Chaves importantes em `config`
| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `usar_ia` | `0` | `1`=OpenRouter, `0`=template fixo |
| `mensagem_padrao` | (interno) | Template com `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{EMOJI}` `{LINK}` |
| `bot_desconto_minimo` | `10` | % mínimo de desconto |
| `bot_preco_maximo` | `500` | R$ máximo |
| `bot_intervalo_entre_ofertas` | `0` | Pausa em segundos entre envios (0/120/300/600/900/1800/3600) |
| `portal_banner_ativo` | `0` | Exibe banner hero no topo do portal |
| `portal_banner_titulo` | `''` | Título do banner do portal |
| `portal_banner_subtitulo` | `''` | Subtítulo do banner do portal |

### Status do Pipeline de Ofertas
```
nova → pronta → enviada
nova → erro_ia
qualquer → rejeitada → [blacklist]
```

## Regras Críticas de Implementação

### SQLite Concorrência
```php
// ORDEM OBRIGATÓRIA — busy_timeout ANTES do journal_mode
$pdo->exec('PRAGMA busy_timeout=15000');
$pdo->exec('PRAGMA journal_mode=WAL');
```

### BASE_URL Dinâmico (multi-ambiente)
```php
// app/helpers.php — nÃO usar /viana/ hardcoded
// Local XAMPP: APP_BASE não definido → BASE = '/viana'
// VPS Docker:  ENV APP_BASE="" → BASE = ''
define('BASE', rtrim(getenv('APP_BASE') !== false ? (string)getenv('APP_BASE') : '/viana', '/'));
// Uso: BASE . '/fila' | BASE . '/config'
```
- `.htaccess` — local (`RewriteBase /viana/`)
- `.htaccess.production` — VPS (`RewriteBase /`), copiado pelo Dockerfile
```python
conn = sqlite3.connect(db_path, timeout=10)
conn.execute('PRAGMA busy_timeout=10000')
conn.execute('PRAGMA journal_mode=WAL')
```

### Logger Python (Windows)
```python
# NÃO usar RotatingFileHandler — tem bug de rename no Windows
fh = logging.FileHandler(LOG_PATH, mode='a', encoding='utf-8')
```

### Log Viewer PHP
```php
// NÃO usar ENT_QUOTES sozinho — retorna string vazia com emojis inválidos
htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
```

### Background Execution
```php
// VPS/Docker — setsid cria nova sessão, bot sobrevive ao PHP encerrar (inclusive durante sleeps longos)
exec(sprintf('setsid python3 %s > /dev/null 2>&1 &', escapeshellarg($script)));

// Windows/XAMPP — NÃO adicionar >> logFile — compete com o FileHandler do Python
$cmd = sprintf('cmd /C start /B /LOW "" "%s" "%s"', $python, $script);
```

### Timezone no Bot Python
```python
# bot/config.py — _BRTFormatter força BRT independente do timezone do servidor
_TZ_BRT = zoneinfo.ZoneInfo('America/Sao_Paulo')
class _BRTFormatter(logging.Formatter):
    def formatTime(self, record, datefmt=None):
        dt = datetime.datetime.fromtimestamp(record.created, tz=_TZ_BRT)
        return dt.strftime(datefmt or '%Y-%m-%d %H:%M:%S')
```

### Retry 429 na ML API (coletor.py)
```python
# 3 tentativas com backoff 60s/120s/180s antes de desistir da keyword
for tentativa in range(3):
    r = requests.get(ML_PRODUCT_SEARCH, ...)
    if r.status_code == 429:
        espera = 60 * (tentativa + 1)
        time.sleep(espera)
        continue
    break
# Delay entre keywords: 2s | Delay entre produtos: 0.3s
```

## URLs do Painel
| Rota | Arquivo | Acesso |
|------|---------|--------|
| `/` | `portal.php` | Público |
| `/v-admin` | `index.php` | Admin |
| `/slides` | `slides.php` | Admin |
| `/links` | `links.php` | Admin |
| `/grupos` | `grupos.php` | Admin |
| `/agenda` | `agenda.php` | Admin |
| `/historico` | `historico.php` | Admin |
| `/fila` | `fila.php` | Admin |
| `/logs` | `logs.php` | Admin |
| `/config` | `config.php` | Admin |
| `/perfil` | `perfil.php` | Admin |
| `/usuarios` | `usuarios.php` | Admin |

## Próximos Passos
1. Confirmar Task Scheduler ativo para execução automática
2. Chatbot de consulta de ofertas via IA no painel
3. Métricas de bot no Dashboard (cards de coletadas/enviadas hoje)
4. Suporte a Amazon/Shopee
