# Sistema Viana Promo — Documentação Técnica
> Atualizado em: 2026-04-22

## Visão Geral
Plataforma de automação de marketing de afiliados fitness. O sistema busca ofertas do Mercado Livre, gera textos de vendas (via IA OpenRouter ou template fixo) e envia automaticamente para grupos WhatsApp via Evolution API — tudo em background, sem travar o painel.

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
```

O bot é disparado via painel (`/viana/fila` → "Rodar Bot Agora") ou agendador do Windows (Task Scheduler).
O PHP dispara Python via **`cmd /C start /B /LOW`** — processo em background de baixa prioridade que **não bloqueia o Apache**.

---

## Estrutura de Arquivos

```
viana/
├── index.php           # Dashboard com métricas gerais
├── links.php           # Gestão manual de links de afiliado
├── grupos.php          # Gerenciar grupos do WhatsApp
├── agenda.php          # Agendamentos de disparo manual
├── historico.php       # Log de envios (paginado, com filtros)
├── fila.php            # Fila de ofertas (cards + botões Enviar / Rejeitar / Limpar)
├── config.php          # Configurações globais (Evolution, ML, OpenRouter/Template, bot)
├── usuarios.php        # Gerenciar usuários do painel
├── perfil.php          # Perfil do usuário (foto, nome, senha)
├── logs.php            # Visualizador de logs ao vivo (polling 4s, UTF-8 seguro)
├── login.php           # Login (rota pública)
├── logout.php          # Logout
│
├── bot/                # Pipeline Python — automação completa
│   ├── main.py         # Orquestrador: roda pipeline ou steps isolados via args
│   ├── coletor.py      # Busca fitness no ML API; verifica blacklist; commita por keyword
│   ├── gerador.py      # Gera copy via IA (OpenRouter) OU template fixo (usar_ia=0)
│   ├── enriquecedor.py # Baixa imagens de produtos para /uploads/
│   ├── emissor.py      # Envia via Evolution API (imagem+texto ou texto puro)
│   ├── config.py       # Lê configs do SQLite + setup_logging (FileHandler simples)
│   └── requirements.txt # requests>=2.31.0, openai>=1.30.0
│
├── api/
│   ├── bot_run.php     # Dispara main.py via cmd /C start /B (não bloqueia)
│   ├── oferta_enviar.php # Envio manual de uma oferta (gera template PHP se necessário)
│   ├── testar_ia.php   # Testa conexão OpenRouter (ping simples)
│   ├── log_tail.php    # Retorna últimas 500 linhas do log em JSON (usado pelo polling)
│   ├── fila.php        # Aprovar / rejeitar ofertas (rejeitar → insere na blacklist)
│   ├── fila_limpar.php # Limpar fila (salva blacklist ANTES de apagar rejeitadas)
│   ├── links.php       # CRUD de links manuais
│   ├── grupos.php      # CRUD de grupos
│   ├── grupos_wpp.php  # Lista grupos ao vivo da Evolution API
│   ├── agenda.php      # CRUD de agendamentos
│   ├── enviar.php      # Disparo manual de um link → grupo
│   ├── upload.php      # Upload de imagem para produto manual
│   ├── ml_auth.php     # Callback OAuth do Mercado Livre
│   └── usuarios.php    # CRUD de usuários
│
├── app/
│   ├── db.php          # getDB(), getConfig(), setConfig() — schema + migrações SQLite
│   ├── evolution.php   # Classe EvolutionAPI {getGroups, sendText, sendMedia}
│   ├── helpers.php     # layoutStart/End, sidebar, toast(), jsonResponse()
│   └── auth.php        # requireLogin(), isLoggedIn(), currentUser()
│
├── cron/
│   └── dispatch.php    # Cron: dispara agendamentos manuais pendentes
│
├── storage/
│   ├── bot.log         # Log do bot Python (FileHandler append, lido pelo logs.php)
│   └── bot.lock        # Lock file — evita execuções duplas
│
├── uploads/            # Imagens de produtos (manuais e baixadas pelo bot)
├── assets/app.css      # Design system (btn-primary, input, label, badges, modais)
├── database/viana.db   # SQLite — banco central (gerado automaticamente)
└── .htaccess           # URLs limpas (mod_rewrite)
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

### Chaves de Configuração (tabela `config`)
| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `evolution_url` | — | URL base da Evolution API |
| `evolution_apikey` | — | Chave de autenticação |
| `evolution_instance` | — | Nome da instância WhatsApp |
| `ml_client_id` | — | Client ID do app Mercado Livre |
| `ml_client_secret` | — | Client Secret do app ML |
| `ml_partner_id` | — | Partner ID para links de afiliado |
| `openrouter_apikey` | — | API Key do OpenRouter (`sk-or-...`) |
| `openrouter_model` | `minimax/minimax-01:free` | Modelo selecionado |
| `usar_ia` | `0` | `1` = gera via OpenRouter; `0` = usa template fixo |
| `mensagem_padrao` | (template interno) | Template com variáveis `{NOME}`, `{PRECO_DE}`, `{PRECO_POR}`, `{DESCONTO}`, `{EMOJI}`, `{LINK}` |
| `bot_desconto_minimo` | `10` | Desconto mínimo (%) para coletar oferta |
| `bot_preco_maximo` | `500` | Preço máximo (R$) para coletar oferta |
| `bot_ativo` | `0` | Flag de habilitar/desabilitar |

### Status do Pipeline de Ofertas
| Status | Significado |
|--------|-------------|
| `nova` | Coletada, aguardando geração de texto |
| `pronta` | Texto gerado (IA ou template), aguardando envio |
| `enviada` | Enviada com sucesso para os grupos |
| `erro_ia` | Falha na geração de texto (OpenRouter) |
| `rejeitada` | Rejeitada manualmente pelo operador |

> **Nota:** `rejeitada` → insere automaticamente na tabela `blacklist` → produto nunca volta.

---

## Pipeline do Bot Python

### 1. `coletor.py`
- Busca nas **categorias fitness** do ML via `/highlights` e **47 palavras-chave** específicas
- Filtra: `desconto >= bot_desconto_minimo` e `preco <= bot_preco_maximo` (exige `original_price` no anúncio — promoções formais da ML)
- Busca até **20 produtos por keyword** (aumentado de 10 para ampliar volume)
- Verifica **blacklist** antes de processar (produtos rejeitados nunca são coletados)
- Deduplicação: ignora produtos coletados nas últimas 48h
- Faz `commit()` após cada keyword → libera lock do SQLite periodicamente
- Pausa de 0.5s entre keywords → não sobrecarrega CPU/rede
- Migra rejeições antigas para a `blacklist` automaticamente a cada execução
- Salva em `ofertas` com `status='nova'`

### 2. `gerador.py` — Dois modos
**Modo IA (`usar_ia=1`)**:
- Processa ofertas com `status='nova'`
- Monta prompt de copywriting fitness (regras de estilo, emojis, WhatsApp markdown)
- Chama o OpenRouter com o modelo configurado no painel
- Fallback automático para template se API Key não estiver configurada

**Modo Template (`usar_ia=0`)**:
- Gera mensagem instantaneamente — sem chamada externa, sem custo
- Usa o template da config ou o padrão interno
- Substitui: `{EMOJI}` `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{LINK}`
- Salva `mensagem_ia` e atualiza `status='pronta'`

### 3. `enriquecedor.py`
- Processa ofertas `status='pronta'` sem imagem local
- Faz download da `imagem_url` para `/uploads/`

### 4. `emissor.py`
- Processa ofertas `status='pronta'` com texto gerado
- Substitui `{LINK}` pelo `url_afiliado` real
- Envia para todos os grupos ativos (prioridade: arquivo local → URL → texto puro)
- Intervalo de 5s entre envios (anti-bloqueio WhatsApp)
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

## Execução em Background (Windows)

O `api/bot_run.php` usa:
```
cmd /C start /B /LOW "" "python" "main.py"
```
- `start /B` — processo totalmente desacoplado do Apache
- `/LOW` — prioridade de CPU baixa (Apache não é afetado)
- Retorna em `<100ms` independente do tempo de execução do bot
- Lock file `storage/bot.lock` evita execuções duplas

---

## Envio Manual de Oferta

O endpoint `api/oferta_enviar.php` permite enviar qualquer oferta da fila imediatamente:
1. Se `usar_ia=0` e `mensagem_ia` vazia → gera template **direto em PHP** (sem Python)
2. Se `usar_ia=1` e `mensagem_ia` vazia → chama `gerador.py` para essa oferta
3. Substitui `{LINK}` pelo link real
4. Envia para todos os grupos ativos via Evolution API
5. Atualiza `status='enviada'`

---

## Blacklist de Produtos

Tabela `blacklist` com `produto_id_externo` (PRIMARY KEY):
- **Criada** automaticamente pelo `db.php` na primeira conexão PHP
- **Também criada** pelo `coletor.py` se ainda não existir (segurança)
- **Populada** via: rejeição manual (botão ✕) → `api/fila.php`
- **Populada** via: "Limpar Rejeitadas" → `api/fila_limpar.php` salva antes de apagar
- **Verificada** pelo `coletor.py` antes de processar cada produto
- **Migração automática**: a cada execução do coletor, produtos `rejeitada` não listados são migrados

---

## Concorrência SQLite (PHP + Python simultâneos)

Configuração crítica para evitar "database is locked":
```php
// app/db.php — ORDEM OBRIGATÓRIA
$pdo->exec('PRAGMA busy_timeout=15000'); // ANTES do journal_mode
$pdo->exec('PRAGMA journal_mode=WAL');
```
```python
# bot/*.py
conn = sqlite3.connect(db_path, timeout=10)
conn.execute('PRAGMA busy_timeout=10000')
conn.execute('PRAGMA journal_mode=WAL')
```
> ⚠️ `busy_timeout` DEVE ser setado antes de qualquer operação de escrita, incluindo `journal_mode=WAL`.

---

## Logs do Sistema

- **Localização:** `storage/bot.log`
- **Handler:** `logging.FileHandler` modo append — evita bug do `RotatingFileHandler` no Windows (não consegue renomear arquivo aberto)
- **Visualização:** Painel → "Logs do Sistema" (`/viana/logs`)
- **Polling:** atualiza automaticamente a cada 4s via `api/log_tail.php`
- **Formato:** `YYYY-MM-DD HH:MM:SS [LEVEL] [MODULE] mensagem`
- **Encoding:** UTF-8 com `ENT_SUBSTITUTE` — emojis Python são tratados sem quebrar o display
- **Limpar log:** botão "Limpar" no painel ou `GET /viana/logs?action=clear`

---

## APIs REST

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/viana/api/bot_run.php` | Inicia o bot em background |
| POST | `/viana/api/oferta_enviar.php` | Envia uma oferta manualmente `{id}` |
| POST | `/viana/api/testar_ia.php` | Testa conexão com OpenRouter |
| GET  | `/viana/api/log_tail.php` | Últimas 500 linhas do log (JSON) |
| POST | `/viana/api/fila.php` | Rejeitar/aprovar oferta `{id, action}` |
| POST | `/viana/api/fila_limpar.php` | Limpar fila `{tipo: rejeitada|todas}` |
| POST | `/viana/api/enviar.php` | Enviar link manual para grupo |
| * | `/viana/api/links.php` | CRUD links manuais |
| * | `/viana/api/grupos.php` | CRUD grupos |
| GET  | `/viana/api/grupos_wpp.php` | Lista grupos da Evolution API |
| * | `/viana/api/usuarios.php` | CRUD usuários |

Todas as respostas seguem o padrão: `{ "ok": true/false, ... }`

---

## URLs do Painel
| URL | Página |
|-----|--------|
| `/viana/` | Dashboard |
| `/viana/links` | Links manuais |
| `/viana/grupos` | Grupos WhatsApp |
| `/viana/agenda` | Agendamentos |
| `/viana/historico` | Histórico de envios |
| `/viana/fila` | Fila de ofertas do bot |
| `/viana/logs` | Logs do bot em tempo real |
| `/viana/config` | Configurações |
| `/viana/perfil` | Perfil do usuário |
| `/viana/usuarios` | Gerenciar usuários |

---

## Modelos de IA Disponíveis (OpenRouter)

### Gratuitos
| Model ID | Nome |
|----------|------|
| `minimax/minimax-01:free` | MiniMax 01 (padrão) |
| `minimax/minimax-m2.5:free` | MiniMax M2.5 |
| `openai/gpt-oss-120b:free` | GPT OSS 120B |
| `moonshotai/moonlight-16b-a3b-instruct:free` | Kimi (Moonshot) |

### Pagos (baixo custo)
| Model ID | Observação |
|----------|------------|
| `deepseek/deepseek-chat-v3-0324` | ~R$0,01/dia de uso típico |
| `google/gemini-flash-1.5` | Rápido e barato |

---

## Multi-Ambiente (Local vs VPS)

O sistema detecta automaticamente em qual ambiente está rodando via `APP_BASE`:

| Variável | Local (XAMPP) | VPS (EasyPanel) |
|----------|--------------|------------------|
| `APP_BASE` | não definida → `'/viana'` | `""` (vazio) |
| `BASE` (constante PHP) | `/viana` | `` (string vazia) |
| URL do painel | `localhost/viana/` | `dominio.com/` |
| `.htaccess` usado | `.htaccess` (`RewriteBase /viana/`) | `.htaccess.production` (`RewriteBase /`) |

### Arquivos de `.htaccess`
| Arquivo | Usado em | `RewriteBase` |
|---------|----------|---------------|
| `.htaccess` | Local XAMPP (ignorado pelo Docker) | `/viana/` |
| `.htaccess.production` | VPS — Dockerfile copia sobre o `.htaccess` | `/` |

### Constante `BASE` (PHP)
Definida no topo de `app/helpers.php`, importada automaticamente em todas as páginas:
```php
define('BASE', rtrim(getenv('APP_BASE') !== false ? (string)getenv('APP_BASE') : '/viana', '/'));
```
Uso: `BASE . '/fila'` → local: `/viana/fila` | VPS: `/fila`

---

## Problemas Conhecidos e Soluções

| Problema | Causa | Solução Implementada |
|----------|-------|----------------------|
| Sistema travava durante bot | Python bloqueava Apache | `cmd /C start /B /LOW` — background real |
| `database is locked` | `busy_timeout` após `journal_mode` | Reordenado: `busy_timeout` sempre primeiro |
| Log vazio (77KB no arquivo) | `htmlspecialchars` com UTF-8 inválido retorna `""` | `ENT_SUBSTITUTE` + `mb_convert_encoding` |
| `PermissionError bot.log` | Shell `>>` e Python disputando o mesmo handle | Removido redirect do shell; Python usa `FileHandler` próprio |
| `RotatingFileHandler` trava | Windows não renomeia arquivo aberto | Substituído por `logging.FileHandler` simples |
| Produtos rejeitados voltando | Blacklist não existia antes; `Limpar Rejeitadas` apagava sem salvar | Blacklist criada; `fila_limpar.php` salva antes de apagar |
| Envio manual bloqueava (`Erro IA`) | Endpoint exigia `mensagem_ia` preenchido | Template gerado em PHP direto, sem Python |

---

## Próximos Passos Sugeridos
1. **Agendamento automático** — confirmar que o Task Scheduler do Windows está ativo
2. **Refinamento de palavras-chave** — monitorar log e ajustar as 31 keywords se chegar produto fora do nicho
3. **Chatbot de consulta** — widget no painel para consultar ofertas via IA
4. **Métricas no Dashboard** — cards de coletadas/enviadas/rejeitadas hoje
