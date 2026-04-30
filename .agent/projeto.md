# Viana Promo — Contexto do Projeto
> ⚠️ OBRIGATÓRIO: Após qualquer alteração, atualize este arquivo E o `docs/sistema.md`.
> Última atualização: 2026-04-29

## Objetivo
Plataforma autônoma de marketing de afiliados fitness. Busca ofertas no Mercado Livre, Magazine Luiza e Shopee, gera copy de vendas com IA (OpenRouter) ou template fixo, e envia automaticamente para grupos WhatsApp via Evolution API — sem intervenção manual. Portal público em `/` exibe as ofertas enviadas (branding **CasaFit**).

## Arquitetura Atual dos Bots
- **Bot ML** é independente: botão próprio na fila, config própria (`bot_ml_*`), lock próprio (`storage/bot_ml.lock`) e log próprio (`storage/bot_ml.log` / `/logs-ml`). Pipeline: Mercado Livre + Magalu → gerar → enriquecer → enviar.
- **Bot Shopee** é independente: botão próprio na fila, config própria (`bot_shopee_*`), lock próprio (`storage/bot_shopee.lock`) e log próprio (`storage/bot_shopee.log` / `/logs-shopee`). Pipeline: Shopee → gerar → enriquecer → enviar.
- Os dois podem coletar/gerar/enriquecer em paralelo. Só o envio é serializado por `storage/emissor.lock`, porque o WhatsApp não deve receber dois emissores ao mesmo tempo.
- `bot_ativo` é legado/pipeline completo. Bot ML e Bot Shopee dependem só de `bot_ml_ativo` e `bot_shopee_ativo`.
- O Docker instala dois crons: `cron/bot_cron_ml.php` e `cron/bot_cron_shopee.php`. O cron legado `cron/bot_cron.php` não deve ser usado em produção normal.
- Geração, enriquecimento e envio são filtrados por fonte: Bot ML só mexe em `ML/MGZ`; Bot Shopee só mexe em `SHP`.

## Tech Stack
- **Frontend:** PHP 8+ com SQLite via PDO, Tailwind CSS CDN, Vanilla JS
- **Bot:** Python 3.9+ — `requests`, `openai` (client OpenRouter), `zoneinfo` (stdlib)
- **Banco:** SQLite (`database/viana.db`) — compartilhado entre PHP e Python
- **APIs:** Evolution API (WhatsApp), Mercado Livre API pública, OpenRouter, Magalu (scraping __NEXT_DATA__), Shopee Affiliate API (GraphQL)
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
├── monitor_crons.php   # Monitor dos crons ML/Shopee — rota /monitor-crons
├── config.php          # Configurações em abas: WhatsApp, Bot ML, Bot Shopee, Fontes, IA & Texto, Portal
├── usuarios.php        # Gestão de usuários
├── perfil.php          # Perfil (foto, nome, senha)
├── logs.php            # Logs legado/completo
├── logs_ml.php         # Logs Bot ML — rota /logs-ml
├── logs_shopee.php     # Logs Bot Shopee — rota /logs-shopee
├── linktree.php        # Admin: gestão de links do bio (tipo Linktree)
├── bio.php             # Página pública tipo Linktree — rota `/bio`
├── termos.php          # Termos de Uso & Privacidade — rota `/termos`
├── 404.php             # Página 404 personalizada CasaFit
├── login.php / logout.php
│
├── bot/
│   ├── main.py             # Orquestrador; --fonte ml e --fonte shopee são bots separados
│   ├── coletor.py          # ML API → blacklist → dedup produto_id 30d + nome_norm 14d → ofertas (~90 keywords)
│   ├── coletor_magalu.py   # Magalu scraping (__NEXT_DATA__) → dedup → ofertas (~70 keywords)
│   ├── coletor_shopee.py   # Shopee Affiliate API (GraphQL) → dedup → ofertas (~70 keywords, roupas fitness/ciclismo em prioridade)
│   ├── gerador.py          # IA (OpenRouter) OU template PHP-compatível
│   ├── enriquecedor.py     # Download imagens → /uploads/
│   ├── emissor.py          # Evolution API → historico → status=enviada (pausa/max_envios configurável)
│   ├── config.py           # get(), set_value(), setup_logging() [_BRTFormatter para BRT]
│   ├── dedup.py            # Módulo centralizado de deduplicação (4 regras — blacklist, preço exato, janela N dias, nome_norm 14d)
│   ├── categorias.py       # Detecção automática de categoria fitness por regex no nome
│   └── requirements.txt
│
├── api/
│   ├── bot_run.php               # Dispara main.py; JSON {fonte:"ml"|"shopee"|""}
│   ├── bot_toggle.php            # Toggle legado: liga/desliga bot_ml_ativo e bot_shopee_ativo juntos
│   ├── bot_lock_clear.php        # Libera locks por fonte: ml | shopee | completo | all
│   ├── oferta_enviar.php         # Envio manual: gera template em PHP se mensagem_ia vazia
│   ├── testar_ia.php             # Ping OpenRouter
│   ├── log_tail.php              # Últimas 500 linhas do log; ?bot=ml|shopee
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
├── cron/
│   ├── bot_cron.php          # Legado/completo
│   ├── bot_cron_fonte.php    # Scheduler compartilhado por fonte
│   ├── bot_cron_ml.php       # Cron independente do Bot ML
│   └── bot_cron_shopee.php   # Cron independente do Bot Shopee
│
├── app/
│   ├── db.php          # getDB() — busy_timeout ANTES de journal_mode (crítico!)
│   ├── evolution.php   # Classe EvolutionAPI (suporta GET/POST/DELETE)
│   ├── helpers.php     # Layout, sidebar, toast(), jsonResponse(), BASE dinâmico
│   └── auth.php        # requireLogin(), currentUser()
│
├── storage/
│   ├── bot.log         # Log legado/completo
│   ├── bot_ml.log      # Log exclusivo do Bot ML
│   ├── bot_shopee.log  # Log exclusivo do Bot Shopee
│   ├── bot.lock        # Lock pipeline completo/legado
│   ├── bot_ml.lock     # Lock exclusivo do Bot ML
│   ├── bot_shopee.lock # Lock exclusivo do Bot Shopee
│   └── emissor.lock    # Lock compartilhado só para envio WhatsApp
│
├── uploads/            # Imagens (manuais, bot, slides, logos, avatares)
├── assets/app.css      # Design system
└── database/viana.db   # SQLite central
```

## Banco de Dados (SQLite)

### Tabelas
`config` | `links` | `grupos` | `agendamentos` | `historico` | `usuarios` | `ofertas` | `blacklist` | `slides` | `bio_links` | `clicks` | `fila_envio`

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
| `bot_ativo` | `1` | Legado/pipeline completo; não bloqueia Bot ML/Shopee |
| `bot_ml_ativo` | `1` | Liga/desliga só o Bot ML |
| `bot_ml_intervalo_horas` | `6` | Intervalo do Bot ML |
| `bot_ml_desconto_minimo` | fallback `bot_desconto_minimo`/`10` | % mínimo de desconto do Bot ML |
| `bot_ml_preco_maximo` | fallback `bot_preco_maximo`/`500` | R$ máximo do Bot ML |
| `bot_ml_max_envios_por_ciclo` | fallback `bot_max_envios_por_ciclo`/`0` | Limite de envios por ciclo do Bot ML |
| `bot_ml_dias_min_reenvio` | fallback `bot_dias_min_reenvio`/`30` | Dedup/reenvio do Bot ML |
| `bot_ml_queda_minima_pct` | fallback `bot_queda_minima_pct`/`5` | Queda mínima para reenvio no Bot ML |
| `bot_shopee_ativo` | `1` | Liga/desliga só o Bot Shopee |
| `bot_shopee_intervalo_horas` | `12` | Intervalo do Bot Shopee |
| `bot_shopee_desconto_minimo` | fallback `bot_desconto_minimo`/`10` | % mínimo de desconto do Bot Shopee |
| `bot_shopee_preco_maximo` | fallback `bot_preco_maximo`/`500` | R$ máximo do Bot Shopee |
| `bot_shopee_max_envios_por_ciclo` | fallback `bot_max_envios_por_ciclo`/`0` | Limite de envios por ciclo do Bot Shopee |
| `bot_shopee_dias_min_reenvio` | fallback `bot_dias_min_reenvio`/`30` | Dedup/reenvio do Bot Shopee |
| `bot_shopee_queda_minima_pct` | fallback `bot_queda_minima_pct`/`5` | Queda mínima para reenvio no Bot Shopee |
| `shopee_app_id` | `''` | App ID da Shopee Affiliate API |
| `shopee_app_secret` | `''` | App Secret da Shopee Affiliate API |
| `shopee_ativo` | `0` | Liga/desliga coleta Shopee |
| `shopee_limite_por_passada` | `50` | Produtos a coletar por keyword (Shopee) |
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
| Enviar | status=enviada | não (dedup por produto_id 30d + nome_norm 14d) |
| Adiar | status=adiada | não (dedup por produto_id 30d + nome_norm 14d) |
| Remover | DELETE da tabela | sim (próximo ciclo do bot) |
| Rejeitar | status=rejeitada + blacklist | nunca |

## Regras Críticas de Implementação

### Bots Independentes
```bash
python3 /app/bot/main.py --fonte ml       # ML + Magalu
python3 /app/bot/main.py --fonte shopee   # Shopee
```
- `main.py` chama `config.set_fonte('ml')` ou `config.set_fonte('shopee')`.
- `config.get('bot_desconto_minimo')` procura primeiro `bot_ml_desconto_minimo` ou `bot_shopee_desconto_minimo`, dependendo da fonte.
- Cada bot escreve no seu log e usa seu lock. Não misturar logs nem configs.
- Se a página de logs mostrar "já está rodando", usar **Liberar Bot** em `/logs-ml` ou `/logs-shopee`.
- As páginas `/logs-ml` e `/logs-shopee` também têm botão **Rodar Bot** para disparar a fonte correta direto do log.
- Em `/config`, cada aba de bot tem **Simular Cron** e **Forçar Agora**; o endpoint `api/cron_test.php` aceita `{fonte:"ml"|"shopee", force:true|false}`.
- Em `/config`, os segmentos de horário/intervalo usam `.seg-option.is-selected` com feedback imediato e uma barra fixa de salvar no topo de cada aba de bot.
- `/monitor-crons` mostra último check/status/mensagem de `bot_cron_ml.php` e `bot_cron_shopee.php`, lock/PID, próximo run e tail do log.

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
Lógica centralizada em `bot/dedup.py` — função `deve_pular(conn, produto_id, preco_por, nome_norm)`. Quatro regras em sequência:

1. **Blacklist permanente** — `produto_id_externo` na tabela `blacklist` → nunca mais coleta.
2. **Preço exato** — produto já existe com exatamente o mesmo preço → ignorado (evita duplicatas de coleta idêntica).
3. **Produto enviado recentemente (N dias)** — enviado dentro da janela `bot_dias_min_reenvio`? Ignorado. Após a janela, só aceita se queda de preço ≥ `bot_queda_minima_pct`%.
4. **nome_norm (14 dias)** — nome sem sabor/cor/peso coletado nos últimos 14 dias → ignorado (evita variações "Whey Chocolate" + "Whey Baunilha" no mesmo período).

```python
# `_normalizar_nome()` remove: pesos (1kg, 500g), sabores (chocolate, morango...),
# indicadores (sabor, cor, tamanho), embalagens (pote, refil, balde)
"SELECT 1 FROM ofertas WHERE nome_norm = ? AND nome_norm != '' AND coletado_em > datetime('now', '-14 days', 'localtime')"
```

A coluna `nome_norm TEXT NOT NULL DEFAULT ''` foi adicionada via `ALTER TABLE` em `app/db.php`. Registros antigos (com `nome_norm=''`) são preenchidos via `_backfill_nome_norm()` no início de cada coleta.

Índices compostos (em `ofertas`):
- `idx_ofertas_prodext_data (produto_id_externo, coletado_em)` — acelera dedup por produto
- `idx_ofertas_nomenorm_data (nome_norm, coletado_em)` — acelera dedup por variação

### Lock Pessimista no Envio Manual
`api/oferta_enviar.php` usa o status `'enviando'` como lock atômico. Antes de processar, faz:

```sql
UPDATE ofertas SET status = 'enviando'
WHERE id = ? AND status IN ('nova','pronta','adiada','enviada','erro_ia')
```

Se `rowCount() === 0`, outra request (ou o `emissor.py` cron) já está processando essa oferta — retorna 409. O `emissor.py` busca apenas `status='pronta'`, então nunca pega `'enviando'`. Em qualquer falha (sem grupos, Evolution off, IA falhou, todos os envios falharam), o lock é liberado voltando para `'pronta'`.

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
| `/logs` | `logs.php` | Admin (legado/completo) |
| `/logs-ml` | `logs_ml.php` | Admin — logs do Bot ML |
| `/logs-shopee` | `logs_shopee.php` | Admin — logs do Bot Shopee |
| `/config` | `config.php` | Admin |
| `/perfil` | `perfil.php` | Admin |
| `/usuarios` | `usuarios.php` | Admin |

### Shopee Affiliate API
```python
# Auth: SHA256 (não HMAC) — sha256(app_id + timestamp + payload + app_secret)
# Endpoint: https://open-api.affiliate.shopee.com.br/graphql
# mutation productOfferV2 → busca produtos por keyword
# mutation generateShortLink → gera link de afiliado rastreável
# Prefix produto_id: "SHP_{itemId}_{shopId}"
# Sub-IDs: ['vianapromo', 'whatsapp']
# Keywords prioritárias no início: roupa para malhar, roupa fitness, conjunto academia,
# camiseta dry fit, roupas para pedalar/ciclismo, bermuda ciclismo, macaquinho ciclismo.
```

## Próximos Passos
1. Cadastrar no parceiromagalu.com.br com CPF e inserir smttag no Config → Magalu
2. Renovar/conectar Mercado Livre em Config → Fontes quando o token expirar
3. Configurar Shopee Affiliate API (app_id + app_secret) em Config → Shopee
4. Configurar cron da VPS como dois bots separados: `--fonte ml` e `--fonte shopee`
5. Métricas de bot no Dashboard (cards de coletadas/enviadas hoje por fonte ML/MGZ/SHP)
6. Chatbot de consulta de ofertas via IA no painel
