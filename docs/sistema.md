# Sistema Viana Promo — Documentação Técnica
> Atualizado em: 2026-04-25

## Visão Geral
Plataforma de automação de marketing de afiliados fitness. O sistema busca ofertas do **Mercado Livre** e **Magazine Luiza**, gera textos de vendas (via IA OpenRouter ou template fixo) e envia automaticamente para grupos WhatsApp via Evolution API. Portal público em `/` (branding **CasaFit**) exibe as ofertas enviadas. Página `/bio` funciona como Linktree editável.

---

## Arquitetura Geral

```
[Mercado Livre API]  → [bot/coletor.py]        ↘
                                                  [SQLite: ofertas / blacklist]
[Magalu scraping]    → [bot/coletor_magalu.py]  ↗
                                                         ↓
[OpenRouter API]*    → [bot/gerador.py]    → [SQLite: mensagem_ia, status=pronta]
  *ou template PHP                                       ↓
                         [bot/enriquecedor.py]  → [uploads/: imagem local]
                                                         ↓
[Evolution API]      ← [bot/emissor.py]    ← [SQLite: status=enviada]
                                                         ↓
                         [SQLite: historico]

Portal público (/) lê SQLite: ofertas WHERE status='enviada'
```

O bot é disparado via painel (`/v-admin` → "Forçar Agora") ou cron Docker a cada 30 min.

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
├── config.php          # Configurações globais (Evolution, ML, Magalu, IA/Template, bot, portal, logo)
├── usuarios.php        # Gerenciar usuários do painel
├── perfil.php          # Perfil do usuário (foto, nome, senha)
├── logs.php            # Visualizador de logs ao vivo (polling 4s, UTF-8 seguro)
├── login.php           # Login (rota pública)
├── logout.php          # Logout
│
├── bot/
│   ├── main.py             # Orquestrador: roda pipeline ou steps isolados via args
│   ├── coletor.py          # ML API; ~90 keywords fitness; dedup por preço; retry 429
│   ├── coletor_magalu.py   # Magalu scraping (__NEXT_DATA__); ~70 keywords; dedup por preço
│   ├── gerador.py          # Gera copy via IA (OpenRouter) OU template fixo (usar_ia=0)
│   ├── enriquecedor.py     # Baixa imagens de produtos para /uploads/
│   ├── emissor.py          # Envia via Evolution API; pausa configurável entre ofertas
│   ├── config.py           # Lê configs do SQLite + setup_logging com timezone BRT forçado
│   └── requirements.txt
│
├── api/
│   ├── bot_run.php               # Dispara main.py via setsid (não bloqueia o PHP)
│   ├── oferta_enviar.php         # Envio manual de uma oferta
│   ├── testar_ia.php             # Testa conexão OpenRouter
│   ├── log_tail.php              # Últimas 500 linhas do log em JSON
│   ├── fila.php                  # rejeitar | adiar | remover | aprovar ofertas
│   ├── fila_limpar.php           # Limpar fila (salva blacklist antes de apagar)
│   ├── bio.php                   # CRUD bio_links (criar/editar/toggle/deletar/perfil)
│   ├── upload_logo.php           # Upload logo do sistema (JPG/PNG/WebP/SVG, max 2MB)
│   ├── links.php                 # CRUD links manuais
│   ├── grupos.php                # CRUD grupos
│   ├── grupos_wpp.php            # Lista grupos ao vivo da Evolution API
│   ├── agenda.php                # CRUD agendamentos
│   ├── enviar.php                # Disparo manual de um link → grupo
│   ├── upload.php                # Upload de imagem (JPG/PNG/WebP, max 5MB)
│   ├── slides.php                # CRUD slides do portal
│   ├── ml_auth.php               # OAuth ML: troca authorization_code por tokens
│   ├── ml_refresh.php            # OAuth ML: renova access_token via refresh_token
│   ├── whatsapp_reconectar.php   # Logout + QR code para trocar número
│   ├── cron_test.php             # Simula/força execução do cron pelo painel
│   └── usuarios.php              # CRUD usuários
│
├── app/
│   ├── db.php          # getDB(), getConfig(), setConfig() — schema + migrações SQLite
│   ├── evolution.php   # Classe EvolutionAPI {getGroups, sendText, sendMedia, logout, getQRCode}
│   ├── helpers.php     # layoutStart/End, sidebar, toast(), jsonResponse(), BASE dinâmico
│   └── auth.php        # requireLogin(), isLoggedIn(), currentUser()
│
├── storage/
│   ├── bot.log         # Log do bot Python (FileHandler append)
│   └── bot.lock        # Lock file — evita execuções duplas
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

### Schema `bio_links`
```sql
CREATE TABLE bio_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL DEFAULT '',
    url TEXT NOT NULL DEFAULT '',
    icone TEXT NOT NULL DEFAULT 'link',   -- whatsapp|instagram|tiktok|youtube|telegram|link|ofertas
    cor TEXT NOT NULL DEFAULT '#059669',
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
| `magalu_smttag` | `''` | ID de parceiro Magalu (parceiromagalu.com.br) |
| `magalu_ativo` | `0` | `1` = ativa coleta Magalu no pipeline |
| `openrouter_apikey` | — | API Key do OpenRouter (`sk-or-...`) |
| `openrouter_model` | `minimax/minimax-01:free` | Modelo selecionado |
| `usar_ia` | `0` | `1` = gera via OpenRouter; `0` = usa template fixo |
| `mensagem_padrao` | (template) | Template com `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{EMOJI}` `{LINK}` |
| `bot_desconto_minimo` | `10` | Desconto mínimo (%) para coletar oferta |
| `bot_preco_maximo` | `500` | Preço máximo (R$) para coletar oferta |
| `bot_ativo` | `0` | Liga/desliga agendamento automático |
| `bot_intervalo_horas` | `6` | Intervalo entre ciclos completos do bot |
| `bot_ultimo_run` | — | Timestamp da última execução |
| `bot_intervalo_entre_ofertas` | `0` | Pausa em minutos entre cada oferta enviada |
| `portal_banner_ativo` | `1` | Exibe o banner hero no portal público |
| `portal_banner_titulo` | — | Título do banner |
| `portal_banner_subtitulo` | — | Subtítulo do banner |
| `system_logo_url` | `''` | URL pública do logo enviado |
| `system_logo_path` | `''` | Caminho físico do logo no servidor |
| `bio_nome` | `CasaFit Ofertas` | Nome exibido na página /bio |
| `bio_descricao` | `''` | Descrição exibida no /bio |
| `bio_avatar_path` | `''` | Caminho do avatar da página /bio |

### Status do Pipeline de Ofertas
| Status | Significado |
|--------|-------------|
| `nova` | Coletada, aguardando geração de texto |
| `pronta` | Texto gerado, aguardando envio |
| `enviada` | Enviada com sucesso para os grupos |
| `erro_ia` | Falha na geração de texto (OpenRouter) |
| `adiada` | Escondida temporariamente — pode ser enviada manualmente depois |
| `rejeitada` | Rejeitada manualmente → migrada para blacklist permanente |

**Ações disponíveis na fila:**
| Botão | Ícone | Efeito | Bot recoleta? |
|-------|-------|--------|--------------|
| Enviar | avião verde | Envia agora pro WhatsApp | não |
| Adiar | relógio laranja | `status=adiada`, some da fila, fica na aba "Adiadas" | não (dedup por preço) |
| Remover | lixo vermelho | `DELETE` da tabela, sem blacklist | **sim** (próximo ciclo) |
| Rejeitar | círculo riscado | `status=rejeitada` + blacklist permanente | nunca |

---

## Pipeline do Bot Python

### `main.py` — Modos de Execução
```bash
python main.py              # pipeline completo (ML + Magalu + gerar + enriquecer + enviar)
python main.py --coletar    # só coleta (ML + Magalu)
python main.py --gerar      # só gera textos
python main.py --enriquecer # só baixa imagens
python main.py --enviar     # só envia
```

### 1. `coletor.py` — Mercado Livre
- Busca via `/highlights` (categoria Esportes) + **~90 palavras-chave** fitness
- Categorias cobertas: Suplementos, Equipamentos de cardio (esteira, bike, elíptico), Musculação (barras, anilhas, racks, bancos), Roupas, Calçados, Acessórios
- Dedup: **produto + preço** — mesmo produto com mesmo `preco_por` é ignorado indefinidamente
- Retry automático em 429: aguarda 60s/120s/180s
- Delay de **2s** entre keywords, **0.3s** entre produtos

### 2. `coletor_magalu.py` — Magazine Luiza
- Scraping via `__NEXT_DATA__` do Next.js — extrai JSON embutido no HTML
- Tenta 4 caminhos no JSON: `data.products`, `search.products`, `products`, `initialState.search.products`
- **~70 palavras-chave** fitness (mesmo universo do ML)
- Link de afiliado: `url + ?smttag={ID}&utm_source=parceiro&utm_medium=afiliado`
- Prefixo no banco: `MGZ_` (ex: `MGZ_123456`)
- Dedup: mesmo esquema produto + preço
- Delay de **3s** entre keywords; retry 3x em 429

### 3. `gerador.py` — Dois modos
**Modo IA (`usar_ia=1`):** chama OpenRouter com modelo configurado; fallback automático para template.

**Modo Template (`usar_ia=0`):** gera mensagem instantaneamente sem chamada externa.

### 4. `enriquecedor.py`
Download da `imagem_url` para `/uploads/`.

### 5. `emissor.py`
- Processa ofertas `status='pronta'`, envia para todos os grupos ativos
- Prioridade: arquivo local → URL externa → texto puro
- Intervalo de **5s** entre grupos (anti-bloqueio WhatsApp)
- Pausa **configurável** entre ofertas (`bot_intervalo_entre_ofertas`)

---

## Renovação do Token ML (crítico)

```python
# ML rotaciona o refresh_token a cada uso — se o novo não for salvo, o antigo é inválido
# _salvar_tokens() usa WAL + busy_timeout + 5 retries com backoff exponencial (1/2/4/8/16s)
# obter_token() tem 3 retries HTTP antes de desistir
```

| Token | Duração | Rotaciona? |
|-------|---------|-----------|
| `access_token` | 6 horas | Não (renovado via refresh) |
| `refresh_token` | ~6 meses | **Sim** — cada uso gera um novo |

Se o SQLite estiver travado quando `_salvar_tokens()` rodar, o novo refresh_token é perdido e o bot perde acesso na próxima execução. Por isso usa WAL + 5 tentativas com backoff.

---

## Portal Público (`portal.php`) — CasaFit

Página pública em `/` — não requer login.

### Componentes
| Componente | Descrição |
|-----------|-----------|
| Header fixo | Logo CasaFit + link Grupo WhatsApp + ícone Instagram |
| Slider | Slides gerenciados em `/slides`; auto-avanço 5s |
| Filtros | Pills (Todas / Suplementos / Roupas / Calçados / Equipamentos / Acessórios) |
| Grid de ofertas | 2–6 colunas responsivas; badge ML (laranja) / MGZ (azul) |
| Paginação | 24 por página |
| Social proof | Notificação "Fulano acabou de pegar essa oferta" — aparece 10s após carregar, repete a cada 45s, dura 6s; 100 nomes brasileiros aleatórios |
| Footer | Links WhatsApp, Instagram, /bio, /termos; "Dev by lmiguelviana" |

### Tiers de cor do badge de desconto
| Faixa | Cor |
|-------|-----|
| 5–24% | `emerald-600` (verde) |
| 25–49% | `amber-400` (âmbar) |
| 50%+ | `rose-500` (vermelho) |

---

## Página /bio — Linktree

`bio.php` (público) exibe avatar, nome, descrição e botões de link com ícones e cores personalizadas.
`linktree.php` (admin) permite criar/editar/reordenar/toggle de cada link e editar perfil (avatar, nome, bio).

**Ícones disponíveis:** `whatsapp` | `instagram` | `tiktok` | `youtube` | `telegram` | `link` | `ofertas`

API: `api/bio.php` — ações `criar` | `editar` | `toggle` | `deletar` | `perfil` (CSRF + login obrigatório)

---

## Execução em Background (Linux/Docker)

```php
// setsid cria nova sessão — processo sobrevive ao término do PHP
exec(sprintf('setsid python3 %s > /dev/null 2>&1 &', escapeshellarg($script)));
```

---

## Agendamento Automático (VPS/Docker)

Cron Docker a cada 30 min → `cron/bot_cron.php`:
1. `bot_ativo != 1` → sai
2. `now < proximo_run` → sai
3. Lock ativo → sai
4. Grava `bot_ultimo_run = now`, lança `setsid python3 main.py`

---

## Concorrência SQLite

```php
// ORDEM OBRIGATÓRIA — busy_timeout ANTES do journal_mode
$pdo->exec('PRAGMA busy_timeout=15000');
$pdo->exec('PRAGMA journal_mode=WAL');
```

---

## Roteamento (`.htaccess`)

**Regra crítica:** a rota `^/?$` (raiz) DEVE vir ANTES da condição `-f/-d` (que serve arquivos existentes). Caso contrário o Apache serve `index.php` como DirectoryIndex em vez do `portal.php`.

```apache
RewriteRule ^/?$  portal.php [L]          # ← ANTES
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]                        # ← DEPOIS
```

---

## URLs do Sistema

### Públicas
| URL | Página |
|-----|--------|
| `/` | Portal CasaFit de achadinhos fitness |
| `/bio` | Página Linktree pública |
| `/termos` | Termos de Uso & Privacidade |

### Admin (requer login)
| URL | Página |
|-----|--------|
| `/v-admin` | Dashboard |
| `/fila` | Fila de ofertas do bot |
| `/slides` | Gestão de slides do portal |
| `/linktree` | Gestão do bio/Linktree |
| `/links` | Links manuais |
| `/grupos` | Grupos WhatsApp |
| `/agenda` | Agendamentos |
| `/historico` | Histórico de envios |
| `/logs` | Logs do bot em tempo real |
| `/config` | Configurações (Evolution, ML, Magalu, IA, Bot, Banner, Logo) |
| `/perfil` | Perfil do usuário |
| `/usuarios` | Gerenciar usuários |

---

## APIs REST

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `BASE/api/bot_run.php` | Inicia o bot em background |
| POST | `BASE/api/oferta_enviar.php` | Envia uma oferta manualmente `{id}` |
| POST | `BASE/api/fila.php?action=rejeitar` | Blacklist permanente `{id}` |
| POST | `BASE/api/fila.php?action=adiar` | Adiar oferta (status=adiada) `{id}` |
| POST | `BASE/api/fila.php?action=remover` | Deletar sem blacklist `{id}` |
| POST | `BASE/api/bio.php` | CRUD bio_links `{action: criar|editar|toggle|deletar|perfil}` |
| POST | `BASE/api/upload_logo.php` | Upload do logo do sistema |
| POST | `BASE/api/slides.php` | CRUD slides `{action: criar|editar|toggle|deletar}` |
| POST | `BASE/api/ml_auth.php` | Autentica conta ML via authorization_code |
| POST | `BASE/api/ml_refresh.php` | Renova access_token via refresh_token |
| POST | `BASE/api/whatsapp_reconectar.php` | Logout + QR code `{action: status|logout|qrcode}` |
| POST | `BASE/api/testar_ia.php` | Testa conexão com OpenRouter |
| POST | `BASE/api/cron_test.php` | Simula/força execução do cron `{force: bool}` |
| GET  | `BASE/api/log_tail.php` | Últimas 500 linhas do log (JSON) |
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
| 429 Too Many Requests ML | Muitas requests em sequência | Delay 2s entre keywords + 0.3s entre produtos + retry backoff 60/120/180s |
| Logs com hora errada | VPS em UTC | `_BRTFormatter` com `zoneinfo` força America/Sao_Paulo |
| Bot morria durante sleep | `nohup` não desvincula do PHP no Docker | `setsid` cria nova sessão independente |
| Token ML "desconectava" | `_salvar_tokens()` sem WAL/busy_timeout — lock do SQLite perdia o refresh_token rotacionado | WAL + busy_timeout + 5 retries com backoff exponencial |
| Mesmo produto voltando | Dedup por janela 48h expirava | Dedup por produto + preço — só recoleta se preço cair |
| Portal mostrando dashboard | Rota raiz vinha depois da condição -f/-d no .htaccess | Mover `^/?$` para antes da condição de arquivo/diretório |
| `database is locked` | `busy_timeout` após `journal_mode` | Reordenado: `busy_timeout` sempre primeiro |
| Log vazio com emojis | `htmlspecialchars` com UTF-8 inválido retorna `""` | `ENT_SUBSTITUTE` + `mb_convert_encoding` |
| Produtos rejeitados voltando | Blacklist não existia | Blacklist permanente + `fila_limpar.php` salva antes de apagar |

---

## Multi-Ambiente (Local vs VPS)

| Variável | Local (XAMPP) | VPS (EasyPanel) |
|----------|--------------|------------------|
| `APP_BASE` | não definida → `'/viana'` | `""` (vazio) |
| `BASE` (PHP) | `/viana` | `` (string vazia) |
| URL do portal | `localhost/viana/` | `dominio.com/` |
| `.htaccess` usado | `.htaccess` | `.htaccess.production` (copiado pelo Dockerfile) |

---

## Próximos Passos
1. Cadastrar no **parceiromagalu.com.br** com CPF e configurar `magalu_smttag`
2. Métricas no Dashboard — cards de coletadas/enviadas hoje por fonte (ML / MGZ)
3. Chatbot de consulta de ofertas via IA no painel
4. Suporte a Amazon/Shopee além do ML
