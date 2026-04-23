# Sistema Viana Promo — Documentação Técnica
> Atualizado em: 2026-04-23

## Visão Geral
Plataforma de automação de marketing de afiliados fitness. O sistema busca ofertas do Mercado Livre, gera textos de vendas (via IA OpenRouter ou template fixo) e envia automaticamente para grupos WhatsApp via Evolution API — tudo em background, sem travar o painel. Inclui portal público de achadinhos em `/` como vitrine das ofertas.

---

## Arquitetura Geral

```
[Mercado Livre API] → [bot/coletor.py] → [SQLite: ofertas / blacklist]
                                              ↓
[OpenRouter API]*   → [bot/gerador.py] → [SQLite: mensagem_ia, status=pronta]
  *ou template PHP                           ↓
                        [bot/enriquecedor.py] → [uploads/: imagem local]
                                              ↓
[Evolution API]     ← [bot/emissor.py] ← [SQLite: status=enviada]
                                              ↓
                        [SQLite: historico]

Portal público (/) lê SQLite: ofertas WHERE status='enviada'
```

O bot é disparado via painel (`/v-admin` → "Forçar Agora") ou cron Docker a cada 30 min.
O PHP dispara Python via **`setsid python3`** — processo com nova sessão, completamente desvinculado do Apache.

---

## Estrutura de Arquivos

```
viana/
├── portal.php          # Portal público de achadinhos (raiz /) — sem login
├── index.php           # Dashboard admin (rota /v-admin)
├── slides.php          # Gestão de slides do portal (admin)
├── links.php           # Gestão manual de links de afiliado
├── grupos.php          # Gerenciar grupos do WhatsApp
├── agenda.php          # Agendamentos de disparo manual
├── historico.php       # Log de envios (paginado, com filtros)
├── fila.php            # Fila de ofertas (cards + botões Enviar / Rejeitar / Limpar)
├── config.php          # Configurações globais (Evolution, ML, OpenRouter/Template, bot, portal)
├── usuarios.php        # Gerenciar usuários do painel
├── perfil.php          # Perfil do usuário (foto, nome, senha)
├── logs.php            # Visualizador de logs ao vivo (polling 4s, UTF-8 seguro)
├── login.php           # Login (rota pública) → redireciona para /v-admin
├── logout.php          # Logout
│
├── bot/                # Pipeline Python — automação completa
│   ├── main.py         # Orquestrador: roda pipeline ou steps isolados via args
│   ├── coletor.py      # Busca fitness no ML API; retry 429; verifica blacklist; 60+ keywords
│   ├── gerador.py      # Gera copy via IA (OpenRouter) OU template fixo (usar_ia=0)
│   ├── enriquecedor.py # Baixa imagens de produtos para /uploads/
│   ├── emissor.py      # Envia via Evolution API; pausa configurável entre ofertas
│   ├── config.py       # Lê configs do SQLite + setup_logging com timezone BRT forçado
│   └── requirements.txt
│
├── api/
│   ├── bot_run.php             # Dispara main.py via setsid (não bloqueia, sobrevive ao PHP)
│   ├── oferta_enviar.php       # Envio manual de uma oferta
│   ├── testar_ia.php           # Testa conexão OpenRouter
│   ├── log_tail.php            # Últimas 500 linhas do log em JSON
│   ├── fila.php                # Aprovar / rejeitar ofertas
│   ├── fila_limpar.php         # Limpar fila (salva blacklist antes)
│   ├── links.php               # CRUD links manuais
│   ├── grupos.php              # CRUD grupos
│   ├── grupos_wpp.php          # Lista grupos ao vivo da Evolution API
│   ├── agenda.php              # CRUD agendamentos
│   ├── enviar.php              # Disparo manual de um link → grupo
│   ├── upload.php              # Upload de imagem (JPG/PNG/WebP, max 5MB)
│   ├── slides.php              # CRUD slides do portal (criar/editar/toggle/deletar)
│   ├── ml_auth.php             # OAuth ML: troca authorization_code por tokens
│   ├── ml_refresh.php          # OAuth ML: renova access_token via refresh_token
│   ├── whatsapp_reconectar.php # Logout + QR code para trocar número (action=status|logout|qrcode)
│   └── usuarios.php            # CRUD usuários
│
├── app/
│   ├── db.php          # getDB(), getConfig(), setConfig() — schema + migrações SQLite
│   ├── evolution.php   # Classe EvolutionAPI {getGroups, sendText, sendMedia, logout, getQRCode}
│   ├── helpers.php     # layoutStart/End, sidebar, toast(), jsonResponse(), BASE dinâmico
│   └── auth.php        # requireLogin(), isLoggedIn(), currentUser()
│
├── cron/
│   ├── bot_cron.php    # Cron: decide se roda o bot (bot_ativo + intervalo + lock)
│   └── dispatch.php    # Cron: dispara agendamentos manuais pendentes
│
├── storage/
│   ├── bot.log         # Log do bot Python (FileHandler append)
│   └── bot.lock        # Lock file — evita execuções duplas
│
├── uploads/            # Imagens de produtos (manuais, bot e slides)
├── assets/app.css      # Design system (btn-primary, input, label, badges, modais)
├── database/viana.db   # SQLite — banco central
├── Dockerfile          # Ubuntu 22.04 + Apache + PHP 8.1 + Python3 + Cron
├── .htaccess           # Dev (XAMPP): RewriteBase /viana/
└── .htaccess.production # VPS (Docker): RewriteBase /
```

---

## Banco de Dados (SQLite)

### Tabelas
| Tabela | Descrição |
|--------|-----------|
| `config` | Chave-valor para todas as configurações do painel e do bot |
| `links` | Links de afiliado inseridos manualmente |
| `grupos` | Grupos WhatsApp com group_jid |
| `agendamentos` | Agendamentos de disparo (link → grupo × dias/horário) |
| `historico` | Log de todos os envios (manuais e automáticos) |
| `usuarios` | Usuários do painel (nome, email, senha bcrypt, foto_path) |
| `ofertas` | Ofertas coletadas pelo bot com status de pipeline |
| `blacklist` | IDs de produtos rejeitados — nunca coletados novamente |
| `slides` | Slides do portal público (imagem, titulo, subtitulo, link, ordem) |

### Schema `slides`
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

### Chaves de Configuração (tabela `config`)
| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `evolution_url` | — | URL base da Evolution API |
| `evolution_apikey` | — | Chave de autenticação |
| `evolution_instance` | — | Nome da instância WhatsApp |
| `ml_client_id` | — | Client ID do app Mercado Livre |
| `ml_client_secret` | — | Client Secret do app ML |
| `ml_partner_id` | — | Partner ID para links de afiliado |
| `ml_access_token` | — | Token de acesso ML (dura 6h; renovado automaticamente) |
| `ml_refresh_token` | — | Token de renovação ML (dura ~6 meses; rotacionado a cada uso) |
| `ml_token_expires` | — | Timestamp Unix de expiração do access_token |
| `ml_user_id` | — | ID do usuário ML autenticado |
| `openrouter_apikey` | — | API Key do OpenRouter (`sk-or-...`) |
| `openrouter_model` | `minimax/minimax-01:free` | Modelo selecionado |
| `usar_ia` | `0` | `1` = gera via OpenRouter; `0` = usa template fixo |
| `mensagem_padrao` | (template interno) | Template com `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{EMOJI}` `{LINK}` |
| `bot_desconto_minimo` | `10` | Desconto mínimo (%) para coletar oferta |
| `bot_preco_maximo` | `500` | Preço máximo (R$) para coletar oferta |
| `bot_ativo` | `0` | Flag de habilitar/desabilitar o agendamento automático |
| `bot_intervalo_horas` | `6` | Intervalo entre ciclos completos do bot |
| `bot_ultimo_run` | — | Timestamp da última execução |
| `bot_intervalo_entre_ofertas` | `0` | Pausa em minutos entre cada oferta enviada (0 = sem pausa) |
| `portal_banner_ativo` | `1` | Exibe o banner hero no portal público |
| `portal_banner_titulo` | `Melhores Ofertas Fitness` | Título do banner |
| `portal_banner_subtitulo` | — | Subtítulo do banner |

### Status do Pipeline de Ofertas
| Status | Significado |
|--------|-------------|
| `nova` | Coletada, aguardando geração de texto |
| `pronta` | Texto gerado (IA ou template), aguardando envio |
| `enviada` | Enviada com sucesso para os grupos |
| `erro_ia` | Falha na geração de texto (OpenRouter) |
| `rejeitada` | Rejeitada manualmente → migrada para blacklist |

---

## Pipeline do Bot Python

### 1. `coletor.py`
- Busca nas **categorias fitness** do ML via `/highlights` e **60+ palavras-chave** específicas
- Categorias: Suplementos, Equipamentos de academia, Roupas fitness, Calçados esportivos, Acessórios
- Retry automático em 429: aguarda 60s/120s/180s antes de desistir da keyword
- Delay de **2s** entre keywords, **0.3s** entre produtos por keyword
- Filtra: `desconto >= bot_desconto_minimo` e `preco <= bot_preco_maximo`
- Verifica blacklist + deduplicação 48h antes de processar
- Faz `commit()` após cada keyword → libera lock do SQLite

### 2. `gerador.py` — Dois modos
**Modo IA (`usar_ia=1`):** chama OpenRouter com modelo configurado; fallback automático para template.

**Modo Template (`usar_ia=0`):** gera mensagem instantaneamente sem chamada externa. Variáveis: `{EMOJI}` `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{LINK}`

### 3. `enriquecedor.py`
- Download da `imagem_url` para `/uploads/`

### 4. `emissor.py`
- Processa ofertas `status='pronta'`, envia para todos os grupos ativos
- Prioridade: arquivo local → URL externa → texto puro
- Intervalo de **5s** entre grupos (anti-bloqueio WhatsApp)
- Pausa **configurável** entre ofertas (`bot_intervalo_entre_ofertas` minutos)
- Atualiza `status='enviada'` e registra no `historico`

### `main.py` — Modos de Execução
```bash
python main.py              # pipeline completo
python main.py --coletar    # só coleta
python main.py --gerar      # só gera textos
python main.py --enriquecer # só baixa imagens
python main.py --enviar     # só envia
```

---

## Execução em Background (Linux/Docker)

```php
// setsid cria nova sessão — processo sobrevive ao término do PHP
exec(sprintf('setsid python3 %s > /dev/null 2>&1 &', escapeshellarg($script)));
```
- `setsid` — nova sessão de processo, completamente desacoplada do Apache/PHP
- Bot continua rodando durante sleeps longos (ex: 5 min entre ofertas)
- Lock file `storage/bot.lock` evita execuções duplas

---

## Portal Público (`portal.php`)

Página pública em `/` (raiz do site) — não requer login.

### Componentes
| Componente | Descrição |
|-----------|-----------|
| Header fixo | Logo + busca por nome |
| Banner hero | Gradient emerald, editável em `/config` → "Banner do Portal" |
| Slider | Imagens gerenciadas em `/slides` (admin); auto-avanço 5s; dots + setas |
| Filtros | Pills de categoria (Todas / Suplementos / Roupas / Calçados / Equipamentos / Acessórios) |
| Grid de ofertas | 2-6 colunas responsivas; badge de desconto com 3 tiers de cor |
| Paginação | 24 por página |
| Footer | Emerald com tagline |

### Tiers de cor do badge de desconto
| Faixa | Cor |
|-------|-----|
| 5–24% | `emerald-600` (verde) |
| 25–49% | `amber-400` (âmbar) |
| 50%+ | `rose-500` (vermelho) |

### Detecção de categoria
Feita por regex no nome do produto — sem campo extra no banco.

---

## Reconectar WhatsApp (Trocar Número)

Endpoint `api/whatsapp_reconectar.php` com 3 ações: `status` / `logout` / `qrcode`.

### Fluxo no painel
1. Botão "Reconectar WhatsApp" → modal de confirmação
2. Loading → chama `logout` + `qrcode`
3. QR code exibido — polling 3s detecta `state=open`
4. Sucesso ou erro com retry

---

## Agendamento Automático (VPS/Docker)

Cron Docker a cada 30 min → `cron/bot_cron.php`:
1. `bot_ativo != 1` → sai
2. `now < proximo_run` → sai
3. Lock ativo → sai
4. Grava `bot_ultimo_run = now`, lança `setsid python3 main.py`

> **Fix Docker:** `start.sh` criado com `printf` (não `echo`) para que `\n` seja interpretado como quebra de linha — sem isso o cron não iniciava.

---

## Autenticação Mercado Livre (OAuth2)

| Token | Duração | Renovado por |
|-------|---------|--------------|
| `access_token` | 6 horas | `refresh_token` (automático) |
| `refresh_token` | ~6 meses | Cada uso gera um novo (rotação) |

O `coletor.py` chama `obter_token()` a cada execução — renova automaticamente se necessário. O painel oferece botão "Renovar Token" para renovação manual.

---

## Concorrência SQLite

```php
// ORDEM OBRIGATÓRIA — busy_timeout ANTES do journal_mode
$pdo->exec('PRAGMA busy_timeout=15000');
$pdo->exec('PRAGMA journal_mode=WAL');
```

---

## URLs do Sistema

### Portal Público
| URL | Página |
|-----|--------|
| `/` | Portal de achadinhos fitness (público) |

### Admin (requer login)
| URL | Página |
|-----|--------|
| `/v-admin` | Dashboard |
| `/links` | Links manuais |
| `/grupos` | Grupos WhatsApp |
| `/agenda` | Agendamentos |
| `/historico` | Histórico de envios |
| `/fila` | Fila de ofertas do bot |
| `/slides` | Gestão de slides do portal |
| `/logs` | Logs do bot em tempo real |
| `/config` | Configurações (Evolution, ML, IA, Bot, Banner) |
| `/perfil` | Perfil do usuário |
| `/usuarios` | Gerenciar usuários |
| `/login` | Login (redireciona para `/v-admin`) |

---

## APIs REST

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `BASE/api/bot_run.php` | Inicia o bot em background |
| POST | `BASE/api/oferta_enviar.php` | Envia uma oferta manualmente `{id}` |
| POST | `BASE/api/slides.php` | CRUD slides `{action: criar|editar|toggle|deletar}` |
| POST | `BASE/api/ml_auth.php` | Autentica conta ML via authorization_code |
| POST | `BASE/api/ml_refresh.php` | Renova access_token via refresh_token |
| POST | `BASE/api/whatsapp_reconectar.php` | Logout + QR code `{action: status|logout|qrcode}` |
| POST | `BASE/api/testar_ia.php` | Testa conexão com OpenRouter |
| GET  | `BASE/api/log_tail.php` | Últimas 500 linhas do log (JSON) |
| POST | `BASE/api/fila.php` | Rejeitar/aprovar oferta |
| POST | `BASE/api/fila_limpar.php` | Limpar fila `{tipo: rejeitada|todas}` |
| POST | `BASE/api/upload.php` | Upload de imagem (JPG/PNG/WebP, max 5MB) |
| POST | `BASE/api/enviar.php` | Enviar link manual para grupo |
| * | `BASE/api/links.php` | CRUD links manuais |
| * | `BASE/api/grupos.php` | CRUD grupos |
| GET  | `BASE/api/grupos_wpp.php` | Lista grupos da Evolution API |
| * | `BASE/api/usuarios.php` | CRUD usuários |

Todas as respostas: `{ "ok": true/false, ... }` via `jsonResponse()`

---

## Problemas Conhecidos e Soluções

| Problema | Causa | Solução |
|----------|-------|---------|
| 429 Too Many Requests ML | Muitas requests em sequência (0.5s era pouco) | Delay 2s entre keywords + 0.3s entre produtos + retry backoff (60/120/180s) |
| Logs com hora errada (+3h) | VPS em UTC, usuário em BRT | `_BRTFormatter` com `zoneinfo` força America/Sao_Paulo nos logs |
| Cron não iniciava no Docker | `echo '...\n...'` com aspas simples não interpreta `\n` | `printf` que interpreta `\n` corretamente |
| Bot morria durante sleep | `nohup` não desvincula do PHP em Docker | `setsid` cria nova sessão independente |
| HTTP 404 no logout Evolution | `request()` sem handling DELETE enviava como GET | `CURLOPT_CUSTOMREQUEST = 'DELETE'` adicionado |
| Sistema travando durante bot | Python bloqueava Apache | `setsid ... &` — processo em background |
| `database is locked` | `busy_timeout` após `journal_mode` | Reordenado: `busy_timeout` sempre primeiro |
| Log vazio (77KB no arquivo) | `htmlspecialchars` com UTF-8 inválido retorna `""` | `ENT_SUBSTITUTE` + `mb_convert_encoding` |
| Produtos rejeitados voltando | Blacklist não existia | Blacklist permanente + `fila_limpar.php` salva antes de apagar |
| Token ML "expirava todo dia" | PHP só verificava `access_token` (6h) | Status tripartido + botão Renovar + `ml_refresh.php` |

---

## Multi-Ambiente (Local vs VPS)

| Variável | Local (XAMPP) | VPS (EasyPanel) |
|----------|--------------|------------------|
| `APP_BASE` | não definida → `'/viana'` | `""` (vazio) |
| `BASE` (PHP) | `/viana` | `` (string vazia) |
| URL do portal | `localhost/viana/` | `dominio.com/` |
| `.htaccess` usado | `.htaccess` | `.htaccess.production` (copiado pelo Dockerfile) |

---

## Próximos Passos Sugeridos
1. Métricas no Dashboard — cards de coletadas/enviadas/rejeitadas hoje
2. Chatbot de consulta de ofertas via IA no painel
3. Suporte a Amazon/Shopee além do ML
