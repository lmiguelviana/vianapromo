# Viana Promo — Contexto do Projeto
> ⚠️ OBRIGATÓRIO: Após qualquer alteração, atualize este arquivo E o `docs/sistema.md`.
> Última atualização: 2026-04-22

## Objetivo
Plataforma autônoma de marketing de afiliados fitness. Busca ofertas no Mercado Livre, gera copy de vendas com IA (OpenRouter) ou template fixo, e envia automaticamente para grupos WhatsApp via Evolution API — sem intervenção manual.

## Tech Stack
- **Frontend:** PHP 8+ com SQLite via PDO, Tailwind CSS CDN, Vanilla JS
- **Bot:** Python 3.14+ — `requests`, `openai` (client OpenRouter)
- **Banco:** SQLite (`database/viana.db`) — compartilhado entre PHP e Python
- **APIs:** Evolution API (WhatsApp), Mercado Livre API pública, OpenRouter
- **Background:** `cmd /C start /B /LOW` — Python roda desacoplado do Apache

## Design System
- **Cor primária:** Emerald (`emerald-600`, `emerald-700`)
- **🚫 Proibido:** Roxo, violeta, índigo em qualquer elemento visual
- **Fonte:** Inter (Google Fonts)
- **Componentes:** `.btn-primary`, `.input`, `.label` (via `assets/app.css`)
- **APIs:** sempre retornam `{ "ok": true/false, ... }` via `jsonResponse()`

## Estrutura de Arquivos

```
viana/
├── index.php           # Dashboard
├── links.php           # Links manuais de afiliado
├── grupos.php          # Grupos WhatsApp
├── agenda.php          # Agendamentos de disparo
├── historico.php       # Log de envios
├── fila.php            # Fila de ofertas (Enviar / Rejeitar / Limpar)
├── config.php          # Configurações (Evolution, ML, IA/Template, filtros)
├── usuarios.php        # Gestão de usuários
├── perfil.php          # Perfil (foto, nome, senha)
├── logs.php            # Logs ao vivo (polling 4s, UTF-8 seguro)
├── login.php / logout.php
│
├── bot/
│   ├── main.py         # Orquestrador (pipeline completo ou steps isolados)
│   ├── coletor.py      # ML API → blacklist check → 48h dedup → ofertas
│   ├── gerador.py      # IA (OpenRouter) OU template PHP-compatível
│   ├── enriquecedor.py # Download imagens → /uploads/
│   ├── emissor.py      # Evolution API → historico → status=enviada
│   ├── config.py       # get(), set_value(), setup_logging() [FileHandler, não Rotating]
│   └── requirements.txt
│
├── api/
│   ├── bot_run.php       # Dispara main.py via cmd /C start /B /LOW (não bloqueia)
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
│   ├── ml_auth.php       # OAuth callback ML
│   └── usuarios.php      # CRUD usuários
│
├── app/
│   ├── db.php          # getDB() — busy_timeout ANTES de journal_mode (crítico!)
│   ├── evolution.php   # Classe EvolutionAPI
│   ├── helpers.php     # Layout, sidebar, toast(), jsonResponse()
│   └── auth.php        # requireLogin(), currentUser()
│
├── storage/
│   ├── bot.log         # Log do Python (FileHandler append — sem rotação)
│   └── bot.lock        # Lock anti-execução-dupla
│
├── uploads/            # Imagens (manuais e bot)
├── assets/app.css      # Design system
└── database/viana.db   # SQLite central
```

## Banco de Dados (SQLite)

### Tabelas
`config` | `links` | `grupos` | `agendamentos` | `historico` | `usuarios` | `ofertas` | `blacklist`

### Tabela `blacklist` (nova)
```sql
CREATE TABLE blacklist (
    produto_id_externo TEXT PRIMARY KEY,
    motivo TEXT NOT NULL DEFAULT 'rejeitado',
    criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
)
```
Populada por: rejeição manual, `fila_limpar.php`, migração automática do `coletor.py`.

### Chaves importantes em `config`
| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `usar_ia` | `0` | `1`=OpenRouter, `0`=template fixo |
| `mensagem_padrao` | (interno) | Template com `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{EMOJI}` `{LINK}` |
| `bot_desconto_minimo` | `10` | % mínimo de desconto |
| `bot_preco_maximo` | `500` | R$ máximo |

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
// NÃO adicionar >> logFile — compete com o FileHandler do Python
$cmd = sprintf('cmd /C start /B /LOW "" "%s" "%s"', $python, $script);
```

## URLs do Painel
`/viana/` | `/viana/links` | `/viana/grupos` | `/viana/agenda` | `/viana/historico` | `/viana/fila` | `/viana/logs` | `/viana/config` | `/viana/perfil` | `/viana/usuarios`

## Próximos Passos
1. Confirmar Task Scheduler ativo para execução automática
2. Chatbot de consulta de ofertas via IA no painel
3. Métricas de bot no Dashboard (cards de coletadas/enviadas hoje)
4. Suporte a Amazon/Shopee
