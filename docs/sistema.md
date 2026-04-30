# Sistema Viana Promo — Documentação Técnica
> Atualizado em: 2026-04-29

## Visão Geral

Plataforma de automação de marketing de afiliados fitness. O sistema busca ofertas do **Mercado Livre**, **Magazine Luiza** e **Shopee**, gera textos de vendas (via IA OpenRouter ou template fixo) e envia automaticamente para grupos WhatsApp via Evolution API. Portal público em `/` (branding **CasaFit**) exibe as ofertas enviadas. Página `/bio` funciona como Linktree editável.

---

## Arquitetura Geral

```
[Mercado Livre API]  → [bot/coletor.py]         ↘
[Magalu scraping]    → [bot/coletor_magalu.py]   →  [SQLite: ofertas / blacklist]
[Shopee Aff. API]    → [bot/coletor_shopee.py]   ↗
                                                            ↓
                          [bot/dedup.py]  ← aplicado por cada coletor
                          [bot/categorias.py] ← detecta categoria do produto
                                                            ↓
[OpenRouter API]*    → [bot/gerador.py]    →  [SQLite: mensagem_ia, status=pronta]
  *ou template PHP                                          ↓
                          [bot/enriquecedor.py]  →  [uploads/: imagem local]
                                                            ↓
[Evolution API]      ← [bot/emissor.py]    ←  [SQLite: status=enviada]
                                                            ↓
                          [SQLite: historico / clicks]

Portal público (/) lê SQLite: ofertas WHERE status='enviada'
```

### Dois Bots Independentes

| Bot | Lock | Pipeline | Uso recomendado |
|-----|------|----------|-----------------|
| **Bot ML** (`--fonte ml`) | `bot_ml.lock` | ML + Magalu → gerar → enriquecer → enviar | A cada 6h |
| **Bot Shopee** (`--fonte shopee`) | `bot_shopee.lock` | Shopee → gerar → enriquecer → enviar | A cada 12h |
| **Bot Completo** (sem arg) | `bot.lock` | Todos → gerar → enriquecer → enviar | Legado/manual |

Regra principal: **ML e Shopee são bots separados de ponta a ponta**. Cada um tem cron, botão, configuração, lock, log e processo próprios. Eles podem coletar/gerar/enriquecer em paralelo. A única trava compartilhada é o `emissor.lock`, porque o WhatsApp deve receber apenas um emissor por vez; se um bot já estiver enviando, o outro aborta apenas a etapa de envio e tenta novamente no próximo ciclo.

Importante: mesmo usando a mesma tabela `ofertas`, cada pipeline filtra sua própria fonte:
- Bot ML gera/enriquece/envia somente `fonte IN ('ML', 'MGZ')`
- Bot Shopee gera/enriquece/envia somente `fonte = 'SHP'`

---

## Estrutura de Arquivos

```
viana/
├── portal.php          # Portal público CasaFit (raiz /) — sem login
├── index.php           # Dashboard admin (rota /v-admin)
├── slides.php          # Gestão de slides do portal (admin)
├── linktree.php        # Gestão do bio/Linktree (admin) — rota /linktree
├── bio.php             # Página pública tipo Linktree — rota /bio
├── termos.php          # Termos de Uso & Privacidade — rota /termos
├── 404.php             # Página 404 personalizada CasaFit
├── links.php           # Gestão manual de links de afiliado
├── grupos.php          # Gerenciar grupos do WhatsApp
├── agenda.php          # Agendamentos de disparo manual
├── historico.php       # Log de envios (paginado, com filtros)
├── fila.php            # Fila de ofertas (Enviar / Adiar / Remover / Rejeitar)
│                       # Botões: Bot ML | Bot Shopee | Liberar Lock | Pausar/Ligar bot
├── config.php          # Configurações — navegação por abas:
│                       #   WhatsApp | Bot ML | Bot Shopee | Fontes | IA & Texto | Portal
├── usuarios.php        # Gerenciar usuários do painel
├── perfil.php          # Perfil do usuário (foto, nome, senha)
├── logs.php            # Visualizador legado de logs ao vivo
├── logs_ml.php         # Logs Bot ML — /logs-ml (storage/bot_ml.log)
├── logs_shopee.php     # Logs Bot Shopee — /logs-shopee (storage/bot_shopee.log)
├── login.php           # Login (rota pública)
├── logout.php          # Logout
│
├── bot/
│   ├── main.py             # Orquestrador; --fonte ml|shopee → bots independentes
│   ├── coletor.py          # ML API; ~90 keywords fitness; usa dedup.py + categorias.py
│   ├── coletor_magalu.py   # Magalu scraping (__NEXT_DATA__); ~70 keywords; usa dedup.py
│   ├── coletor_shopee.py   # Shopee Affiliate API (GraphQL + SHA256); ~70 keywords; usa dedup.py
│   ├── gerador.py          # Gera copy via IA (OpenRouter) OU template fixo
│   ├── enriquecedor.py     # Baixa imagens de produtos para /uploads/
│   ├── emissor.py          # Envia via Evolution API; emissor.lock anti-paralelo
│   ├── dedup.py            # Módulo centralizado de deduplicação (4 regras)
│   ├── categorias.py       # Detecção automática de categoria fitness por regex
│   ├── config.py           # Lê configs do SQLite + setup_logging com timezone BRT
│   └── requirements.txt
│
├── api/
│   ├── bot_run.php               # Dispara main.py; param fonte="ml"|"shopee"|""
│   ├── bot_toggle.php            # Toggle legado: liga/desliga bot_ml_ativo e bot_shopee_ativo juntos
│   ├── bot_lock_clear.php        # Remove locks por fonte: ml | shopee | completo | all
│   ├── oferta_enviar.php         # Envio manual com lock pessimista (status=enviando)
│   ├── testar_ia.php             # Testa conexão OpenRouter
│   ├── log_tail.php              # Últimas 500 linhas do log em JSON
│   ├── fila.php                  # rejeitar | adiar | remover | aprovar ofertas
│   ├── fila_limpar.php           # Limpar fila (salva blacklist antes de apagar)
│   ├── bio.php                   # CRUD bio_links
│   ├── upload_logo.php           # Upload logo do sistema (JPG/PNG/WebP/SVG, max 2MB)
│   ├── links.php                 # CRUD links manuais
│   ├── grupos.php                # CRUD grupos
│   ├── grupos_wpp.php            # Lista grupos ao vivo da Evolution API
│   ├── agenda.php                # CRUD agendamentos
│   ├── enviar.php                # Disparo manual de um link → grupo
│   ├── upload.php                # Upload de imagem (JPG/PNG/WebP, max 5MB)
│   ├── slides.php                # CRUD slides do portal
│   ├── click.php                 # Rastreador de cliques → redireciona para afiliado
│   ├── ml_auth.php               # OAuth ML: troca authorization_code por tokens
│   ├── ml_refresh.php            # OAuth ML: renova access_token via refresh_token
│   ├── whatsapp_reconectar.php   # Logout + QR code para trocar número
│   ├── cron_test.php             # Simula/força execução do cron pelo painel
│   └── usuarios.php              # CRUD usuários
│
├── cron/
│   ├── bot_cron.php          # Cron legado/pipeline completo (não recomendado em produção)
│   ├── bot_cron_fonte.php    # Scheduler compartilhado por fonte
│   ├── bot_cron_ml.php       # Cron exclusivo do Bot ML
│   └── bot_cron_shopee.php   # Cron exclusivo do Bot Shopee
│
├── app/
│   ├── db.php          # getDB(), getConfig(), setConfig() — schema + migrações SQLite
│   ├── evolution.php   # Classe EvolutionAPI {getGroups, sendText, sendMedia, logout, getQRCode}
│   ├── helpers.php     # layoutStart/End, sidebar, toast(), jsonResponse(), BASE dinâmico
│   └── auth.php        # requireLogin(), isLoggedIn(), currentUser()
│
├── storage/
│   ├── bot.log          # Log legado do pipeline completo
│   ├── bot_ml.log       # Log exclusivo do Bot ML
│   ├── bot_shopee.log   # Log exclusivo do Bot Shopee
│   ├── bot.lock         # Lock bot completo
│   ├── bot_ml.lock      # Lock bot ML independente
│   ├── bot_shopee.lock  # Lock bot Shopee independente
│   └── emissor.lock     # Lock exclusivo do emissor (evita envios paralelos)
│
├── uploads/            # Imagens de produtos, logos, avatares, slides
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
| `bio_links` | Links da página /bio (tipo Linktree) — icone, cor, ordem |
| `clicks` | Rastreamento de cliques em ofertas (oferta_id, clicado_em) |
| `fila_envio` | Fila de envio agendado (não usado ativamente ainda) |

### Tabela `ofertas` — colunas relevantes

```sql
fonte              TEXT   -- 'ML' | 'MGZ' | 'SHP'
produto_id_externo TEXT   -- 'ML_123', 'MGZ_456', 'SHP_itemId_shopId'
nome               TEXT
nome_norm          TEXT   -- nome sem sabor/cor/peso (usado no dedup)
categoria          TEXT   -- proteinas | creatina | pre_treino | roupas | equipamentos | etc.
preco_de           REAL
preco_por          REAL
desconto_pct       INTEGER
url_afiliado       TEXT
imagem_url         TEXT
imagem_path        TEXT
mensagem_ia        TEXT
status             TEXT   -- nova | pronta | enviando | enviada | erro_ia | adiada | rejeitada
coletado_em        DATETIME
enviado_em         DATETIME
```

### Chaves de Configuração (`config`)

| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `evolution_url` | — | URL base da Evolution API |
| `evolution_apikey` | — | Chave de autenticação |
| `evolution_instance` | — | Nome da instância WhatsApp |
| `ml_client_id` | — | Client ID do app Mercado Livre |
| `ml_client_secret` | — | Client Secret do app ML |
| `ml_partner_id` | — | Partner ID para links de afiliado ML |
| `ml_access_token` | — | Token de acesso ML (dura 6h) |
| `ml_refresh_token` | — | Token de renovação ML (rotacionado a cada uso) |
| `ml_token_expires` | — | Timestamp Unix de expiração |
| `magalu_smttag` | `''` | ID de parceiro Magalu (parceiromagalu.com.br) |
| `magalu_ativo` | `0` | `1` = ativa coleta Magalu |
| `shopee_app_id` | `''` | App ID da Shopee Affiliate API |
| `shopee_app_secret` | `''` | App Secret da Shopee Affiliate API |
| `shopee_ativo` | `0` | `1` = ativa coleta Shopee |
| `shopee_limite_por_passada` | `50` | Máx. produtos Shopee por keyword por ciclo |
| `openrouter_apikey` | — | API Key do OpenRouter |
| `openrouter_model` | `minimax/minimax-01:free` | Modelo de IA selecionado |
| `usar_ia` | `0` | `1` = gera via OpenRouter; `0` = usa template fixo |
| `mensagem_padrao` | (template) | Template com `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{EMOJI}` `{LINK}` |
| `site_url` | `''` | URL de produção — links enviados passam por `/api/click.php?id=X` |
| `bot_ativo` | `1` | Legado/pipeline completo; não bloqueia Bot ML/Shopee |
| `bot_ml_ativo` | `1` | Liga/desliga o Bot ML |
| `bot_ml_intervalo_horas` | fallback `bot_intervalo_horas` / `6` | Intervalo entre ciclos do Bot ML |
| `bot_ml_desconto_minimo` | fallback `bot_desconto_minimo` / `10` | Desconto mínimo (%) para o Bot ML coletar |
| `bot_ml_preco_maximo` | fallback `bot_preco_maximo` / `500` | Preço máximo (R$) para o Bot ML coletar |
| `bot_ml_intervalo_entre_ofertas` | fallback `bot_intervalo_entre_ofertas` / `0` | Pausa em minutos entre envios do Bot ML |
| `bot_ml_max_envios_por_ciclo` | fallback `bot_max_envios_por_ciclo` / `0` | Limite de envios por ciclo do Bot ML |
| `bot_ml_dias_min_reenvio` | fallback `bot_dias_min_reenvio` / `30` | Dias para bloquear reenvio no Bot ML |
| `bot_ml_queda_minima_pct` | fallback `bot_queda_minima_pct` / `5` | Queda mínima para reenvio no Bot ML |
| `bot_shopee_ativo` | `1` | Liga/desliga o Bot Shopee |
| `bot_shopee_intervalo_horas` | fallback `bot_intervalo_horas` / `12` | Intervalo entre ciclos do Bot Shopee |
| `bot_shopee_desconto_minimo` | fallback `bot_desconto_minimo` / `10` | Desconto mínimo (%) para o Bot Shopee coletar |
| `bot_shopee_preco_maximo` | fallback `bot_preco_maximo` / `500` | Preço máximo (R$) para o Bot Shopee coletar |
| `bot_shopee_intervalo_entre_ofertas` | fallback `bot_intervalo_entre_ofertas` / `0` | Pausa em minutos entre envios do Bot Shopee |
| `bot_shopee_max_envios_por_ciclo` | fallback `bot_max_envios_por_ciclo` / `0` | Limite de envios por ciclo do Bot Shopee |
| `bot_shopee_dias_min_reenvio` | fallback `bot_dias_min_reenvio` / `30` | Dias para bloquear reenvio no Bot Shopee |
| `bot_shopee_queda_minima_pct` | fallback `bot_queda_minima_pct` / `5` | Queda mínima para reenvio no Bot Shopee |
| `portal_banner_ativo` | `1` | Exibe o banner hero no portal |
| `portal_banner_titulo` | — | Título do banner |
| `portal_banner_subtitulo` | — | Subtítulo do banner |
| `system_logo_url` | `''` | URL pública do logo enviado |
| `system_logo_path` | `''` | Caminho físico do logo no servidor |
| `bio_nome` | `CasaFit Ofertas` | Nome exibido em /bio |
| `bio_descricao` | `''` | Descrição exibida em /bio |
| `bio_avatar_path` | `''` | Caminho do avatar da página /bio |

### Status do Pipeline de Ofertas

| Status | Significado |
|--------|-------------|
| `nova` | Coletada, aguardando geração de texto |
| `pronta` | Texto gerado, aguardando envio |
| `enviando` | Lock pessimista ativo (envio manual em curso) |
| `enviada` | Enviada com sucesso |
| `erro_ia` | Falha na geração de texto |
| `adiada` | Escondida temporariamente — pode ser enviada manualmente |
| `rejeitada` | Rejeitada → blacklist permanente |

**Ações na fila:**

| Botão | Efeito | Bot recoleta? |
|-------|--------|--------------|
| Enviar | Envia agora + lock pessimista | não (dedup 30d) |
| Adiar | `status=adiada` | não (dedup 30d) |
| Remover | `DELETE` sem blacklist | sim (próximo ciclo) |
| Rejeitar | `status=rejeitada` + blacklist | nunca |

---

## Pipeline do Bot Python

### `main.py` — Modos de Execução

```bash
# Dois bots independentes (recomendado para produção)
python main.py --fonte ml       # Bot ML: ML + Magalu → gerar → enriquecer → enviar
python main.py --fonte shopee   # Bot Shopee: Shopee → gerar → enriquecer → enviar

# Pipeline completo (um só processo)
python main.py

# Steps avulsos
python main.py --coletar        # só coleta (ML + Magalu + Shopee)
python main.py --gerar          # só gera textos
python main.py --enriquecer     # só baixa imagens
python main.py --enviar         # só envia

# Exemplo cron na VPS
0 */6  * * *  python3 /app/bot/main.py --fonte ml
0 */12 * * *  python3 /app/bot/main.py --fonte shopee
```

### `dedup.py` — Deduplicação Centralizada

Função principal: `deve_pular(conn, produto_id, preco_por, nome_norm) → (bool, str)`

Quatro regras aplicadas em sequência:

| Regra | Condição | Resultado |
|-------|----------|-----------|
| 1. Blacklist permanente | `produto_id_externo` na tabela `blacklist` | Ignorado para sempre |
| 2. Preço exato | Mesmo produto com exatamente o mesmo preço já existe | Ignorado |
| 3. Janela de reenvio | Produto enviado há menos de `bot_dias_min_reenvio` dias | Ignorado; após janela, só aceita se queda ≥ `bot_queda_minima_pct`% |
| 4. Nome normalizado 14d | `nome_norm` coletado nos últimos 14 dias | Ignorado (evita variações sabor/cor/peso) |

**`nome_norm`** remove: pesos (1kg, 500g), sabores (chocolate, baunilha...), indicadores (sabor, cor, tamanho), embalagens (pote, refil, balde). Exemplo: "Whey Protein Chocolate 900g" → "whey protein".

Índices no banco para performance:
- `idx_ofertas_prodext_data (produto_id_externo, coletado_em)`
- `idx_ofertas_nomenorm_data (nome_norm, coletado_em)`

### `categorias.py` — Categorização Automática

Detecta categoria pelo nome do produto via regex:

| Categoria | Exemplos |
|-----------|----------|
| `proteinas` | whey, albumina, caseína, hipercalórico |
| `creatina` | creatina |
| `pre_treino` | pré-treino, cafeína |
| `aminoacidos` | bcaa, glutamina |
| `vitaminas` | vitamina D, ômega 3, colágeno, multivitamínico |
| `snacks` | pasta de amendoim, barra proteica |
| `equipamentos` | haltere, anilha, barra, kettlebell, banco, supino |
| `cardio` | esteira, bicicleta ergométrica, elíptico |
| `roupas` | legging, top, camiseta dry fit, bermuda |
| `acessorios` | coqueteleira, luva, munhequeira, cinto |
| `monitoramento` | smartwatch, balança, monitor cardíaco |
| `outros` | tudo que não se enquadra acima |

Usado pelos 3 coletores. O portal mapeia essas categorias para os filtros visuais (suplementos / roupas / equipamentos / acessórios).

### `coletor.py` — Mercado Livre

- Busca via `/highlights` (Esportes e Fitness) + **~90 palavras-chave**
- Prefixo no banco: `ML_`
- Delay: 2s entre keywords, 0.3s entre produtos
- Retry automático em 429: backoff 60s / 120s / 180s

### `coletor_magalu.py` — Magazine Luiza

- Scraping via `__NEXT_DATA__` do Next.js (JSON embutido no HTML)
- Tenta 4 caminhos no JSON: `data.products`, `search.products`, `products`, `initialState.search.products`
- **~70 palavras-chave** fitness
- Link de afiliado: `url + ?smttag={ID}&utm_source=parceiro&utm_medium=afiliado`
- Prefixo no banco: `MGZ_`
- Delay: 3s entre keywords, retry 3x em 429

### `coletor_shopee.py` — Shopee

- API GraphQL: `https://open-api.affiliate.shopee.com.br/graphql`
- **Autenticação SHA256** (não HMAC): `sha256(app_id + timestamp + payload + app_secret)`
- `mutation productOfferV2` → busca produtos por keyword
- `mutation generateShortLink` → gera link de afiliado rastreável
- **~70 palavras-chave** fitness, com prioridade para roupas de academia e ciclismo/pedalar
- Inclui buscas como: `roupa para malhar feminina`, `roupa fitness masculina`, `conjunto academia`, `camiseta dry fit`, `roupa ciclismo`, `bermuda ciclismo acolchoada`, `macaquinho ciclismo`
- Prefixo no banco: `SHP_{itemId}_{shopId}`
- Sub-IDs do link: `['vianapromo', 'whatsapp']`
- Limite configurável por passada (`shopee_limite_por_passada`, padrão 50)

### `emissor.py`

- Verifica WhatsApp conectado antes de começar (`GET /instance/connectionState/...`)
- Busca ofertas `status='pronta'` ordenadas por `desconto_pct DESC`
- Aplica o limite do bot ativo via `config.get('bot_max_envios_por_ciclo')`; em runtime isso resolve para `bot_ml_max_envios_por_ciclo` ou `bot_shopee_max_envios_por_ciclo`
- Adquire `emissor.lock` — se outro emissor já roda, aborta apenas a etapa de envio daquele bot
- Envia para todos os grupos ativos; intervalo de **5s** entre grupos
- Só marca `status='enviada'` se pelo menos 1 grupo recebeu com sucesso
- Aborta se detectar "Connection Closed" na resposta da Evolution API

### Config por Fonte (`bot/config.py`)

`main.py` chama `config.set_fonte('ml')` ou `config.set_fonte('shopee')` antes do pipeline. A partir daí, chamadas genéricas como:

```python
config.get('bot_desconto_minimo')
config.get('bot_preco_maximo')
config.get('bot_max_envios_por_ciclo')
```

procuram primeiro a chave com prefixo da fonte (`bot_ml_*` ou `bot_shopee_*`). Se ela não existir, caem no valor legado `bot_*`. Isso mantém compatibilidade com configs antigas e permite que cada bot tenha seus próprios limites.

---

## Rastreamento de Cliques

Quando `site_url` está configurado, os links enviados no WhatsApp passam por:

```
https://seusite.com/api/click.php?id=X
→ registra click na tabela clicks
→ redireciona para url_afiliado
```

Funciona em qualquer origem: WhatsApp, portal, bio. O `oferta_id` é registrado com timestamp.

---

## Renovação do Token ML

| Token | Duração | Rotaciona? |
|-------|---------|-----------|
| `access_token` | 6 horas | Não |
| `refresh_token` | ~6 meses | **Sim** — cada uso gera um novo |

**Crítico:** se `_salvar_tokens()` falhar (SQLite travado), o refresh_token antigo já foi invalidado pelo ML e o bot perde acesso. Por isso usa WAL + `busy_timeout` + 5 retries com backoff exponencial (1/2/4/8/16s).

---

## Lock Files

| Arquivo | Criado por | Protege |
|---------|-----------|---------|
| `storage/bot.lock` | `main.py` (sem --fonte) | Pipeline completo |
| `storage/bot_ml.lock` | `main.py --fonte ml` | Bot ML |
| `storage/bot_shopee.lock` | `main.py --fonte shopee` | Bot Shopee |
| `storage/emissor.lock` | `emissor.py` | Envio WhatsApp (único ativo por vez) |

**Zombie lock:** se o processo morreu sem limpar o lock, `_pid_vivo()` verifica `/proc/{pid}/cmdline` no Linux para confirmar que o PID pertence ao bot (não a um processo do kernel como PID 28 = kthreadd). Lock zumbi é removido automaticamente no próximo run.

**Pelo painel:** cada página de log tem botão **Liberar Bot**:
- `/logs-ml` chama `api/bot_lock_clear.php` com `{fonte: "ml"}` e remove `bot_ml.lock`
- `/logs-shopee` chama `api/bot_lock_clear.php` com `{fonte: "shopee"}` e remove `bot_shopee.lock`

O endpoint também tenta parar o processo Python correspondente quando o PID do lock realmente pertence ao bot daquela fonte.

## Logs dos Bots

| Página | Arquivo | Fonte |
|--------|---------|-------|
| `/logs-ml` | `storage/bot_ml.log` | Bot ML |
| `/logs-shopee` | `storage/bot_shopee.log` | Bot Shopee |
| `/logs` | `storage/bot.log` ou seletor legado | Pipeline completo/legado |

As duas páginas novas são independentes, aparecem separadas no menu lateral e usam polling a cada 4s via `api/log_tail.php?bot=ml` ou `api/log_tail.php?bot=shopee`.

Cada página de log tem ações próprias:
- **Liberar Bot**: remove o lock daquela fonte e tenta parar o PID se ele for realmente `main.py --fonte ...`.
- **Rodar Bot**: chama `api/bot_run.php` com a fonte correta.

---

## Lock Pessimista no Envio Manual

`api/oferta_enviar.php` usa `status='enviando'` como trava atômica:

```sql
UPDATE ofertas SET status = 'enviando'
WHERE id = ? AND status IN ('nova','pronta','adiada','enviada','erro_ia')
```

Se `rowCount() === 0`, outra request já está processando → retorna 409. Em qualquer falha (sem grupos, Evolution off, IA falhou, todos os envios falharam), reverte para `'pronta'`.

---

## Página de Configurações

Navegação por **6 abas** (sticky, mobile-friendly):

| Aba | Formulário | Conteúdo |
|-----|-----------|----------|
| WhatsApp | `salvar_evolution` | URL, APIKey, Instância + botão Reconectar QR |
| Bot ML | `salvar_bot_ml` | Toggle do Bot ML, agendamento, filtros, limites e dedup próprios |
| Bot Shopee | `salvar_bot_shopee` | Toggle do Bot Shopee, agendamento, filtros, limites e dedup próprios |
| Fontes | `salvar_ml_creds` / `salvar_magalu` / `salvar_shopee` | Credenciais ML + OAuth, Magalu, Shopee |
| IA & Texto | `salvar_ia` | URL de produção, toggle IA/template, OpenRouter, modelo, mensagem |
| Portal | `salvar_portal` | Logo do sistema (upload JS), banner (título, subtítulo, toggle) |

A aba ativa persiste via `sessionStorage` e é restaurada automaticamente após reload (incluindo após submit de formulário via hidden input `active_tab`).

As abas **Bot ML** e **Bot Shopee** gravam chaves separadas (`bot_ml_*` e `bot_shopee_*`). Os bots por fonte não dependem mais do `bot_ativo` legado.

Cada aba de bot também tem:
- **Simular Cron**: diagnostica se aquele bot rodaria agora sem executar.
- **Forçar Agora**: dispara aquele bot específico (`fonte=ml` ou `fonte=shopee`) ignorando intervalo, mas respeitando pausa e lock.
- Barra fixa **Salvar Bot ML/Shopee** no topo da aba, para salvar horário, intervalo entre ofertas e limites sem precisar rolar até o final.
- Os controles de horário e intervalo são segmentos com feedback visual imediato (`seg-option.is-selected`).

---

## Portal Público (`portal.php`) — CasaFit

Página pública em `/` — não requer login.

| Componente | Descrição |
|-----------|-----------|
| Header fixo | Logo + link Grupo WhatsApp + ícone Instagram |
| Slider | Slides gerenciados em `/slides`; auto-avanço 5s |
| Filtros de categoria | Pills client-side: Todas / Suplementos / Roupas / Calçados / Equipamentos / Acessórios |
| Grid de ofertas | 2–6 colunas responsivas; badge de desconto por faixa |
| Paginação | 24 por página |
| Polling | A cada 30s verifica se chegaram novas ofertas (max_id); pill animado aparece no topo |
| Social proof | Notificação fictícia "Fulano acabou de pegar" — 10s após load, repete a cada 45s |
| Footer | Links WhatsApp, Instagram, /bio, /termos |

**Tiers de badge de desconto:**

| Faixa | Cor |
|-------|-----|
| < 25% | Emerald (verde) |
| 25–49% | Amber (âmbar) |
| ≥ 50% | Rose (vermelho) |

**Categorização no portal:** cada card recebe `data-cat` detectado pelo mapeamento DB → portal. A coluna `categoria` do banco (detectada pelo Python) tem prioridade; se vazia, aplica regex PHP como fallback.

---

## Página /bio — Linktree

`bio.php` (público) exibe avatar, nome, descrição e botões de link com ícones e cores personalizadas.  
`linktree.php` (admin) permite criar/editar/reordenar/toggle e editar perfil (avatar, nome, bio).

**Ícones:** `whatsapp` | `instagram` | `tiktok` | `youtube` | `telegram` | `link` | `ofertas`

---

## Concorrência SQLite

```php
// ORDEM OBRIGATÓRIA — busy_timeout ANTES do journal_mode
$pdo->exec('PRAGMA busy_timeout=15000');
$pdo->exec('PRAGMA journal_mode=WAL');
```

```python
# Python — mesma ordem
conn = sqlite3.connect(db_path, timeout=10)
conn.execute('PRAGMA busy_timeout=10000')
conn.execute('PRAGMA journal_mode=WAL')
```

---

## Execução em Background

```php
// Linux/Docker (VPS)
setsid python3 /app/bot/main.py --fonte ml > /dev/null 2>&1 &

// Windows/XAMPP (dev)
cmd /C start /B /LOW "" "python" "C:\...\main.py" --fonte ml
```

`setsid` cria nova sessão — o processo Python sobrevive ao término do PHP/Apache.

---

## Agendamento Automático (VPS)

Produção trata ML e Shopee como dois crons separados. O Docker instala `/etc/cron.d/viana-promo` com duas linhas independentes:

```cron
*/30 * * * * www-data php /var/www/viana/cron/bot_cron_ml.php >> /dev/null 2>&1
*/30 * * * * www-data php /var/www/viana/cron/bot_cron_shopee.php >> /dev/null 2>&1
```

Cada script acorda a cada 30 minutos, mas só dispara quando o intervalo configurado daquele bot vence (`bot_ml_intervalo_horas` ou `bot_shopee_intervalo_horas`). O cron legado `cron/bot_cron.php` ainda existe para pipeline completo/manual, mas **não é usado pelo Docker em produção**.

Execução direta equivalente:

```bash
0 */6  * * *  python3 /app/bot/main.py --fonte ml
0 */12 * * *  python3 /app/bot/main.py --fonte shopee
```

Cada comando usa seu próprio lock (`bot_ml.lock` ou `bot_shopee.lock`) e seu próprio log (`bot_ml.log` ou `bot_shopee.log`). Os toggles `bot_ml_ativo` e `bot_shopee_ativo` pausam cada fonte sem afetar a outra.

---

## Roteamento (`.htaccess`)

**Regra crítica:** a rota `^/?$` DEVE vir ANTES da condição `-f/-d`:

```apache
RewriteRule ^/?$  portal.php [L]          # ← ANTES
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]                        # ← DEPOIS
```

---

## Multi-Ambiente (Local vs VPS)

| Variável | Local (XAMPP) | VPS (EasyPanel) |
|----------|--------------|------------------|
| `APP_BASE` | não definida → `'/viana'` | `""` (vazio) |
| `BASE` (PHP) | `/viana` | `` (string vazia) |
| URL do portal | `localhost/viana/` | `dominio.com/` |
| `.htaccess` | `.htaccess` | `.htaccess.production` (copiado pelo Dockerfile) |

---

## APIs REST

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `BASE/api/bot_run.php` | Inicia bot; `{fonte: "ml"|"shopee"|""}` |
| POST | `BASE/api/bot_toggle.php` | Toggle legado da fila; liga/desliga `bot_ml_ativo` e `bot_shopee_ativo` juntos |
| POST | `BASE/api/bot_lock_clear.php` | Libera lock por fonte; `{fonte: "ml"|"shopee"|"completo"|"all"}` |
| POST | `BASE/api/oferta_enviar.php` | Envia oferta manualmente `{id}` |
| POST | `BASE/api/fila.php?action=rejeitar` | Blacklist permanente `{id}` |
| POST | `BASE/api/fila.php?action=adiar` | Adiar oferta `{id}` |
| POST | `BASE/api/fila.php?action=remover` | Deletar sem blacklist `{id}` |
| POST | `BASE/api/bio.php` | CRUD bio_links `{action: criar|editar|toggle|deletar|perfil}` |
| POST | `BASE/api/upload_logo.php` | Upload do logo do sistema |
| POST | `BASE/api/slides.php` | CRUD slides `{action: criar|editar|toggle|deletar}` |
| POST | `BASE/api/ml_auth.php` | Autentica conta ML via authorization_code |
| POST | `BASE/api/ml_refresh.php` | Renova access_token |
| POST | `BASE/api/whatsapp_reconectar.php` | `{action: status|logout|qrcode}` |
| POST | `BASE/api/testar_ia.php` | Testa conexão OpenRouter |
| POST | `BASE/api/cron_test.php` | Simula/força cron `{force: bool}` |
| POST | `BASE/api/fila_limpar.php` | Limpar fila `{tipo: rejeitada|todas}` |
| POST | `BASE/api/enviar.php` | Enviar link manual para grupo |
| GET  | `BASE/api/log_tail.php?bot=ml` | Últimas 500 linhas do log do Bot ML |
| GET  | `BASE/api/log_tail.php?bot=shopee` | Últimas 500 linhas do log do Bot Shopee |
| GET  | `BASE/api/click.php?id=X` | Rastreia clique e redireciona |
| GET  | `BASE/api/grupos_wpp.php` | Lista grupos Evolution API ao vivo |
| * | `BASE/api/links.php` | CRUD links manuais |
| * | `BASE/api/grupos.php` | CRUD grupos |
| * | `BASE/api/usuarios.php` | CRUD usuários |
| * | `BASE/api/agenda.php` | CRUD agendamentos |

Todas as respostas: `{ "ok": true/false, ... }` via `jsonResponse()`

---

## Problemas Conhecidos e Soluções

| Problema | Causa | Solução |
|----------|-------|---------|
| "Bot já está rodando (PID 28)" | PID 28 no Linux é kernel (`kthreadd`) — sempre vivo | `_pid_vivo()` verifica `/proc/{pid}/cmdline`; lock limpo pelo painel |
| Bot ML/Shopee fica em "já está rodando" | Lock da fonte ficou com PID antigo (`bot_ml.lock` ou `bot_shopee.lock`) | Usar **Liberar Bot** em `/logs-ml` ou `/logs-shopee`; endpoint remove lock e tenta parar o PID se for do bot correto |
| 429 ML | Muitas requests seguidas | Delay 2s + retry backoff 60/120/180s |
| Logs com hora errada | VPS em UTC | `_BRTFormatter` com `zoneinfo` força America/Sao_Paulo |
| Token ML "desconectava" | `_salvar_tokens()` sem WAL — refresh_token rotacionado era perdido | WAL + busy_timeout + 5 retries com backoff |
| Token ML expirado | Access token do Mercado Livre venceu ou refresh token perdeu validade | Ir em Config → Fontes → Mercado Livre e usar Renovar/Conectar novamente |
| Mesmo produto reenviado | Dedup por janela 48h expirava cedo | 4 regras no `dedup.py`: blacklist, preço exato, 30d por produto, nome_norm 14d |
| Variações (sabores/cores) | IDs externos diferentes passavam pelo dedup | `nome_norm` remove sabor/cor/peso; 14 dias de bloqueio por nome |
| Envio duplicado (manual + cron) | Race condition: dois processos pegavam mesma oferta | Lock pessimista: `UPDATE status='enviando' WHERE status IN (...)` |
| Dois bots enviando juntos | ML e Shopee terminavam coleta ao mesmo tempo | `emissor.lock` — apenas um emissor roda por vez |
| `database is locked` | `busy_timeout` após `journal_mode` | Reordenado: `busy_timeout` sempre primeiro |
| Portal mostrando dashboard | Rota raiz vinha depois da condição `-f/-d` | Mover `^/?$` para antes da condição de arquivo |

---

## Próximos Passos

1. Cadastrar no **parceiromagalu.com.br** (CPF) e inserir `magalu_smttag` em Config → Fontes
2. Configurar **Shopee Affiliate API** (app_id + app_secret) em Config → Fontes → Shopee
3. Configurar cron na VPS com **2 bots independentes** (ML a cada 6h, Shopee a cada 12h)
4. Métricas no Dashboard — cards de coletadas/enviadas hoje por fonte (ML / MGZ / SHP)
5. Chatbot de consulta de ofertas via IA no painel
