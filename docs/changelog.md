# Changelog — Viana Promo

## 2026-04-22 — Renovação Automática de Token ML

### 🔑 Fix: Token ML "expirava todo dia"

**Problema:** O token do Mercado Livre aparecia como "Não conectado" diariamente, exigindo reconexão manual via fluxo OAuth completo.

**Causa raiz:** Confusão entre dois tokens distintos:
- `access_token` — dura **6 horas** (não 1 dia)
- `refresh_token` — dura **~6 meses** e permite renovar sem novo login

O PHP só verificava se o `access_token` estava vivo. Quando ele expirava (6h), mostrava "Não conectado" mesmo com o `refresh_token` válido por meses. Isso levava o operador a refazer o fluxo OAuth desnecessariamente a cada dia.

**Solução implementada:**

1. **`api/ml_refresh.php` (novo)** — endpoint que chama `POST /oauth/token` com `grant_type=refresh_token`, salva o novo `access_token` e rotaciona o `refresh_token` (o ML gera um novo a cada uso)
2. **`config.php`** — três melhorias:
   - Status agora diferencia 3 estados: `🟢 Token ativo` / `🟡 Expirado — renovação disponível` / `🔴 Não conectado`
   - Botão **"Renovar Token"** aparece quando o `access_token` expirou mas o `refresh_token` existe
   - Lógica `$ml_conectado` corrigida: `true` se `access_token` ativo **OU** se `refresh_token` salvo

**Como funciona agora:**
- O `coletor.py` já renovava automaticamente desde sempre (função `obter_token()`)
- O painel agora faz o mesmo via botão — sem precisar reautorizar
- Reconexão manual só necessária se ficar **~6 meses** sem rodar o bot

**Arquivos modificados/criados:**

| Arquivo | Mudança |
|---------|--------|
| `api/ml_refresh.php` | **Novo** — renova token via refresh_token |
| `config.php` | Status tripartido (ativo/expirado/desconectado) + botão Renovar |

---

## 2026-04-22 — Deploy EasyPanel + Fix de URLs

### 🐛 Bug Fix: 404 em produção (VPS/EasyPanel)

**Problema:** O sistema estava hardcoded com o prefixo `/viana/` em todas as URLs internas (sidebar, CSS, logout, redirect de login). No XAMPP local isso funciona porque o app fica em `localhost/viana/`. Na VPS com EasyPanel, o app fica na raiz `/`, então todas as URLs quebravam com 404.

**Causa raiz:** Caminhos hardcoded em `app/helpers.php` e `app/auth.php` — 8 ocorrências de `/viana/`.

**Solução implementada:** Sistema de `BASE` dinâmico via variável de ambiente `APP_BASE`:

```php
// app/helpers.php — lido em todos os includes
define('BASE', rtrim(getenv('APP_BASE') !== false ? (string)getenv('APP_BASE') : '/viana', '/'));
```

| Ambiente | `APP_BASE` | URLs geradas |
|----------|-----------|----------------|
| XAMPP local | não definida → padrão `/viana` | `/viana/fila`, `/viana/config` |
| EasyPanel/VPS | `""` (vazio, via `ENV APP_BASE=""` no Dockerfile) | `/fila`, `/config` |

**Arquivos modificados:**

| Arquivo | Mudança |
|---------|--------|
| `app/helpers.php` | `define('BASE', ...)` no topo; 7 ocorrências de `/viana/` → `BASE . '/'` |
| `app/auth.php` | Redirect de login: `/viana/login.php` → `BASE . '/login'` |
| `.htaccess.production` | **Novo** — `.htaccess` com `RewriteBase /` para VPS (raiz) |
| `Dockerfile` | `ENV APP_BASE=""` + `COPY .htaccess.production .htaccess` (sobrescreve o dev) |

**Como funciona na prática:**
- **Local:** `APP_BASE` não está definido → `getenv()` retorna `false` → `BASE = '/viana'` ✅
- **VPS:** Dockerfile define `ENV APP_BASE=""` → `BASE = ''` → URLs ficam `/fila`, `/config` ✅
- **`.htaccess`:** Dev usa `RewriteBase /viana/`; Dockerfile copia `.htaccess.production` com `RewriteBase /` sobrescrevendo o arquivo local

---

## 2026-04-22 — Sessão de Estabilização

### 🐛 Bug Fixes Críticos

#### Database Locked (`SQLSTATE[HY000]: General error: 5`)
- **Causa:** `PRAGMA busy_timeout` era definido *após* `journal_mode=WAL`, então a primeira escrita (CREATE TABLE) ocorria sem timeout configurado.
- **Fix:** `app/db.php` — reordenado: `busy_timeout=15000` **antes** de `journal_mode=WAL`.
- **Fix:** Todos os scripts Python (`coletor.py`, `emissor.py`) receberam `PRAGMA busy_timeout=10000` antes de qualquer operação.

#### Sistema Travando Durante Execução do Bot
- **Causa:** Bot era executado via PowerShell `Start-Process`, que demora ~2s para iniciar e ainda competia com o Apache.
- **Fix:** `api/bot_run.php` migrado para `cmd /C start /B /LOW` — retorna em `<100ms`, Python roda com prioridade baixa de CPU.
- **Fix adicional:** `coletor.py` agora faz `conn.commit()` após cada keyword (libera o lock do SQLite regularmente) e aguarda 0.5s entre buscas.

#### Log Vazio na Página (arquivo tinha 77KB)
- **Causa 1:** `htmlspecialchars($txt, ENT_QUOTES, 'UTF-8')` retorna string vazia quando encontra bytes UTF-8 inválidos (emojis do Python no Windows).
- **Fix:** Trocado para `ENT_QUOTES | ENT_SUBSTITUTE` + `mb_convert_encoding()`.
- **Causa 2:** `cmd ... >> bot.log` no shell e `RotatingFileHandler` do Python tentavam abrir o mesmo arquivo simultaneamente → `PermissionError`.
- **Fix:** Removido redirect `>>` do shell (`bot_run.php`); Python usa `FileHandler` próprio.
- **Causa 3:** `RotatingFileHandler` no Windows falha ao tentar renomear `bot.log` → `bot.log.1` enquanto o arquivo está aberto.
- **Fix:** Substituído por `logging.FileHandler(mode='a')` simples (`config.py`).

#### Envio Manual Bloqueado ("Texto da IA ainda não gerado")
- **Causa:** `oferta_enviar.php` exigia `mensagem_ia` preenchido, mas todas as ofertas tinham status `erro_ia` com o campo vazio.
- **Fix:** Quando `usar_ia=0`, o endpoint gera o template **diretamente em PHP** sem precisar do Python.

#### Produtos Rejeitados Voltando na Fila
- **Causa:** Blacklist não existia quando os produtos foram rejeitados pela primeira vez. Ao usar "Limpar Rejeitadas", os registros eram apagados sem salvar os IDs.
- **Fix:** `api/fila_limpar.php` agora insere na `blacklist` **antes** de apagar.
- **Fix:** `coletor.py` migra automaticamente todos os produtos `status='rejeitada'` para a blacklist a cada execução.

---

### ✨ Novas Funcionalidades

#### Blacklist Permanente de Produtos
- **Tabela:** `blacklist` (`produto_id_externo` PRIMARY KEY)
- **Criada** pelo `db.php` na primeira conexão PHP e pelo `coletor.py` como fallback.
- **Fluxo:** Rejeitar oferta → `api/fila.php` insere na blacklist → `coletor.py` nunca mais coleta.
- **Proteção:** `ja_coletado()` verifica blacklist com `try/except OperationalError` (seguro se tabela não existir).

#### Gerador Dual: IA ou Template
- **Config:** Toggle "Usar IA para criar mensagens" em `/viana/config` (`usar_ia` = 0 ou 1).
- **Modo IA:** Chama OpenRouter com o modelo configurado; fallback para template se API Key ausente.
- **Modo Template:** Gera mensagem instantaneamente via variáveis. Funciona offline, sem custo.
- **Template padrão:** `{EMOJI} *{NOME}*\n\n~~R$ {PRECO_DE}~~ por apenas *R$ {PRECO_POR}* 🏷️ *{DESCONTO}% OFF*\n\n🔗 ...`
- **Variáveis:** `{NOME}` `{PRECO_DE}` `{PRECO_POR}` `{DESCONTO}` `{EMOJI}` `{LINK}`
- **Emoji automático:** Detectado por palavras no nome do produto (whey, creatina, esteira, etc.)

#### Botão "Testar IA" no Config
- Endpoint: `api/testar_ia.php` — envia ping para OpenRouter e exibe resposta inline.
- Mostra o modelo em uso e confirma se a API Key está funcionando.

#### Envio Manual por Oferta
- Botão **Enviar** em cada card da fila (`fila.php`).
- Endpoint: `api/oferta_enviar.php` — gera texto (template ou IA), substitui `{LINK}`, envia para todos os grupos ativos.
- Atualiza o card visualmente sem recarregar a página (JS inline).

#### Log Ao Vivo com Polling
- `logs.php` atualiza automaticamente a cada **4 segundos** via `api/log_tail.php`.
- Indicador "🟢 ao vivo" no cabeçalho.
- Rola automaticamente para o final a cada atualização.

---

### 📁 Arquivos Criados

| Arquivo | Descrição |
|---------|-----------|
| `api/oferta_enviar.php` | Envio manual de uma oferta com geração de template PHP |
| `api/testar_ia.php` | Ping de teste para OpenRouter |
| `api/log_tail.php` | Endpoint de polling para log ao vivo |

### 📝 Arquivos Modificados

| Arquivo | O que mudou |
|---------|-------------|
| `app/db.php` | busy_timeout antes de journal_mode; tabela `blacklist` adicionada; defaults `usar_ia` e `mensagem_padrao` |
| `api/bot_run.php` | PowerShell → `cmd /C start /B /LOW`; removido redirect `>>` conflitante |
| `api/fila.php` | Rejeitar oferta insere na blacklist |
| `api/fila_limpar.php` | Salva blacklist antes de apagar rejeitadas |
| `bot/coletor.py` | Verifica blacklist; cria tabela se não existir; migra rejeitadas; commit por keyword; sleep 0.5s |
| `bot/gerador.py` | Suporte a modo template (usar_ia=0) além da IA; busy_timeout adicionado |
| `bot/emissor.py` | busy_timeout adicionado |
| `bot/config.py` | `RotatingFileHandler` → `FileHandler` simples (bug Windows); try/except para PermissionError |
| `config.php` | Toggle IA/Template; campo de template editável; botão "Testar IA"; chaves `usar_ia` e `mensagem_padrao` salvas |
| `fila.php` | Botão Enviar por oferta; função JS `enviarOferta()`; botões "Limpar Rejeitadas" e "Limpar Tudo" |
| `logs.php` | Fix UTF-8 (ENT_SUBSTITUTE); remoção de \r Windows; polling 4s ao vivo |
| `docs/sistema.md` | Atualizado completamente (esta sessão) |
| `.agent/projeto.md` | Atualizado completamente (esta sessão) |
