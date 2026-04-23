# Changelog — Viana Promo

## 2026-04-23 — Portal Público, Slides, Fixes de Bot e Infraestrutura

### ✨ Novas Funcionalidades

#### Portal Público de Achadinhos (`portal.php`)
- Nova página pública (sem login) acessível em `/portal` e agora na **raiz `/`**
- Grid responsivo de ofertas com status `enviada` — 2 a 6 colunas conforme tela
- Busca por nome via GET `?q=`
- Filtro por categoria (Suplementos, Roupas, Calçados, Equipamentos, Acessórios) detectado automaticamente por regex no nome do produto
- Badge de desconto em 3 tiers de cor: emerald (5–24%), amber (25–49%), rose (50%+)
- Preço de/por com riscado, tempo relativo de envio ("há 2h")
- Paginação de 24 em 24
- Footer emerald com tagline

#### Banner Editável do Portal (`config.php` + `app/db.php`)
- Seção "Banner do Portal Público" na página de configurações
- Toggle on/off, campo de título e subtítulo editáveis
- Banner renderizado como hero gradient emerald no topo do portal
- Novas chaves: `portal_banner_ativo`, `portal_banner_titulo`, `portal_banner_subtitulo`

#### Slider de Imagens no Portal (`slides.php` + `api/slides.php`)
- Tabela `slides` nova no banco (titulo, subtitulo, imagem_path, link_url, ordem, ativo)
- Página admin `/slides` — CRUD completo com upload de imagem, preview, toggle ativo/inativo, ordenação
- Slider 16:5 fullwidth no portal com setas prev/next, dots e auto-avanço a cada 5s
- Overlay de título e subtítulo sobre a imagem com gradiente
- Slide clicável se `link_url` preenchido
- Não aparece no portal se não houver slides ativos (zero impacto)
- Item "Slides Portal" adicionado à sidebar do admin

#### Intervalo Configurável Entre Ofertas (`emissor.py` + `config.php`)
- Novo campo no painel: pausa entre cada oferta enviada (Sem pausa / 2 / 5 / 10 / 15 / 30 min / 1 hora)
- Nova chave de config: `bot_intervalo_entre_ofertas`
- `emissor.py` usa `enumerate()` para não dormir após a última oferta

---

### 🐛 Bug Fixes

#### Rate Limit 429 da ML API (`bot/coletor.py`)
- **Causa:** pausa de 0.5s entre keywords era insuficiente — bot disparava centenas de requisições em sequência
- **Fix 1:** delay entre keywords: `0.5s → 2s`; delay entre produtos por keyword: `0s → 0.3s`
- **Fix 2 (retry com backoff):** ao receber 429, aguarda 60s/120s/180s e tenta novamente até 3x antes de desistir da keyword
- Aplicado tanto em `buscar_product_ids_keyword` quanto em `buscar_product_ids_highlights`

#### Timezone Errado nos Logs (`bot/config.py`)
- **Causa:** VPS rodava em UTC, logs mostravam 3h adiantados
- **Fix:** classe `_BRTFormatter` com `zoneinfo.ZoneInfo('America/Sao_Paulo')` força timestamps em BRT independente da timezone do servidor

#### Cron Não Iniciava no Docker (`Dockerfile`)
- **Causa:** `echo '...\n...'` com aspas simples não interpreta `\n` como quebra de linha em bash — `start.sh` ficava numa linha só, `service cron start` nunca executava
- **Fix:** `echo` → `printf` que interpreta `\n` corretamente

#### Bot Morria Durante Sleep Entre Ofertas (`api/bot_run.php`, `api/cron_test.php`, `cron/bot_cron.php`)
- **Causa:** `nohup` não desvincula completamente o processo do PHP em ambientes Docker — bot era morto quando o PHP encerrava a requisição
- **Fix:** `nohup python3` → `setsid python3` — cria nova sessão de processo, completamente independente do PHP/Apache

#### Fix DELETE no `evolution.php`
- **Causa:** `request()` não tinha handling para DELETE — enviava como GET → HTTP 404 no logout
- **Fix:** `elseif ($method === 'DELETE') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); }`

#### URLs de API sem `.php` em `slides.php`
- **Causa:** `fetch(BASE + '/api/upload')` e `fetch(BASE + '/api/slides')` — APIs no projeto são acessadas com extensão `.php`
- **Fix:** corrigido para `'/api/upload.php'` e `'/api/slides.php'`

---

### 🔧 Melhorias

#### Portal como Página Principal
- `/` → `portal.php` (público)
- `/v-admin` → `index.php` (dashboard admin)
- Login redireciona para `/v-admin` após autenticação
- Sidebar nav "Dashboard" aponta para `/v-admin`

#### Keywords de Moda Fitness (`bot/coletor.py`)
- Seção de roupas expandida de 8 para 20 keywords
- Adicionadas: legging cintura alta, legging compressão, conjunto feminino, calça jogger, sutiã esportivo, regata, camiseta compressão, blusa moletom treino, jaqueta corta vento, tênis academia feminino, tênis crossfit, kit roupa academia

#### Remoção de Conteúdo Obsoleto (`config.php`)
- Removido bloco "Como rodar o bot" (instruções Windows/XAMPP obsoletas para ambiente VPS/Docker)

---

### 📁 Arquivos Criados

| Arquivo | Descrição |
|---------|-----------|
| `portal.php` | Portal público de achadinhos fitness |
| `slides.php` | Página admin de gestão de slides |
| `api/slides.php` | API CRUD de slides (criar/editar/toggle/deletar) |

### 📝 Arquivos Modificados

| Arquivo | O que mudou |
|---------|-------------|
| `app/db.php` | Tabela `slides`; chaves `portal_banner_*`; `bot_intervalo_entre_ofertas` |
| `app/helpers.php` | Nav "Slides Portal" + ícone `image`; "Dashboard" → `/v-admin` |
| `app/evolution.php` | Suporte a DELETE; métodos `logout()` e `getQRCode()` |
| `bot/coletor.py` | Retry backoff 429; delay 2s entre keywords; 0.3s entre produtos; 20 keywords de roupas |
| `bot/config.py` | `_BRTFormatter` com `zoneinfo` para timezone BRT nos logs |
| `bot/emissor.py` | `INTERVALO_GRUPO_SEGUNDOS`; pausa configurável entre ofertas |
| `config.php` | Seção banner do portal; intervalo entre ofertas (2min adicionado); seção "Como rodar o bot" removida |
| `login.php` | Redirect pós-login → `/v-admin` |
| `Dockerfile` | `echo` → `printf` no start.sh (fix cron) |
| `api/bot_run.php` | `nohup` → `setsid` (process detachment) |
| `api/cron_test.php` | `nohup` → `setsid` |
| `cron/bot_cron.php` | `nohup` → `setsid` |
| `.htaccess` | `/` → portal; `/v-admin` → dashboard; rota `/slides` |
| `.htaccess.production` | Idem acima |

---

## 2026-04-22 — Reconectar WhatsApp via QR Code

### ✨ Nova Funcionalidade

#### Modal de Reconexão WhatsApp (`config.php` + `api/whatsapp_reconectar.php`)
- Permite trocar o número conectado à instância Evolution API sem sair do painel
- 5 telas no modal: confirmação → loading → QR code → sucesso / erro
- Polling a cada 3s para detectar quando o QR é escaneado (`state=open`)
- Auto-refresh do QR a cada 30s (expira no Evolution)
- Endpoint `api/whatsapp_reconectar.php` com 3 ações: `status`, `logout`, `qrcode`

---

## 2026-04-22 — Renovação Automática de Token ML

### 🔑 Fix: Token ML "expirava todo dia"

**Problema:** O token do Mercado Livre aparecia como "Não conectado" diariamente, exigindo reconexão manual via fluxo OAuth completo.

**Causa raiz:** Confusão entre dois tokens distintos:
- `access_token` — dura **6 horas** (não 1 dia)
- `refresh_token` — dura **~6 meses** e permite renovar sem novo login

**Solução implementada:**

1. **`api/ml_refresh.php` (novo)** — renova `access_token` via `refresh_token` sem novo login
2. **`config.php`** — status tripartido: `🟢 Token ativo` / `🟡 Expirado — renovação disponível` / `🔴 Não conectado`

---

## 2026-04-22 — Deploy EasyPanel + Fix de URLs

### 🐛 Bug Fix: 404 em produção

**Solução:** Sistema de `BASE` dinâmico via variável de ambiente `APP_BASE`:
```php
define('BASE', rtrim(getenv('APP_BASE') !== false ? (string)getenv('APP_BASE') : '/viana', '/'));
```

---

## 2026-04-22 — Sessão de Estabilização

### 🐛 Bug Fixes Críticos

- `database is locked` → `busy_timeout` antes de `journal_mode=WAL`
- Sistema travando → `cmd /C start /B /LOW` (background real)
- Log vazio → `ENT_SUBSTITUTE` + `FileHandler` simples
- Produtos rejeitados voltando → blacklist permanente
- Envio manual bloqueado → template gerado em PHP direto

### ✨ Novas Funcionalidades
- Blacklist permanente de produtos
- Gerador dual: IA ou template
- Botão "Testar IA" no Config
- Envio manual por oferta
- Log ao vivo com polling 4s
