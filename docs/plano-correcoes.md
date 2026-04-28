# Plano de Correções — Viana Promo

Análise feita em 2026-04-28 após o fix de dedup (commit `e8ebce0`). Lista de problemas reais encontrados, ordenados por impacto, com plano de correção.

---

## ✅ #1 — Race condition entre `oferta_enviar.php` e `emissor.py`

**Status:** ✔ corrigido

**Arquivo:** [api/oferta_enviar.php](../api/oferta_enviar.php)

**Problema:** quando o usuário aperta "Enviar" na fila e o cron do emissor está rodando no mesmo instante, ambos podem enviar a mesma oferta para o WhatsApp (duplicada).

**Fluxo do bug:**
1. Cron dispara `emissor.py` → busca `status='pronta'` → começa a enviar oferta #42
2. Usuário aperta "Enviar" no front → `oferta_enviar.php` busca oferta #42 (`status != 'rejeitada'`) → começa a enviar de novo
3. Os dois rodam até o fim, dão `UPDATE status='enviada'`
4. WhatsApp recebe a mesma oferta 2x

**Correção:** lock pessimista. No início de `oferta_enviar.php`, fazer um `UPDATE ofertas SET status='enviando' WHERE id=? AND status IN ('nova','pronta','adiada')`. Se `rowCount() === 0`, abortar (alguém já está enviando). No fim, atualiza para `'enviada'`. O `emissor.py` só pega `status='pronta'`, então não pega `'enviando'`.

**Mudança em `app/db.php`:** adicionar `'enviando'` como status válido (na verdade SQLite não enforce check, então só documentar).

---

## ✅ #2 — Falta índice em `ofertas(produto_id_externo, coletado_em)` e `ofertas(nome_norm, coletado_em)`

**Status:** ✔ corrigido

**Arquivo:** [app/db.php](../app/db.php)

**Problema:** o dedup novo faz queries com `WHERE produto_id_externo = ? AND coletado_em >= datetime(...)` e `WHERE nome_norm = ? AND coletado_em > datetime(...)`. Sem índice composto, cada checagem varre a tabela inteira. Com poucas centenas de ofertas é OK, mas com 10k+ vira gargalo no coletor.

**Correção:** adicionar dois índices na migração de `db.php`:

```sql
CREATE INDEX IF NOT EXISTS idx_ofertas_prodext_data ON ofertas(produto_id_externo, coletado_em);
CREATE INDEX IF NOT EXISTS idx_ofertas_nomenorm_data ON ofertas(nome_norm, coletado_em);
```

---

## ⚪ #3 — Tabela `fila_envio` declarada mas nunca usada

**Status:** baixa prioridade

**Arquivo:** [app/db.php:153-160](../app/db.php#L153-L160)

**Problema:** schema cria `fila_envio` mas nenhum código lê/escreve nela. Código morto que confunde quem lê o schema.

**Opções:**
- (a) Remover a tabela
- (b) Começar a usá-la para enfileirar envios em vez de chamar Evolution API direto do request

Opção (a) é mais simples. (b) seria útil se o volume crescer.

---

## ⚪ #4 — `exec("python gerador.py")` síncrono dentro de request HTTP

**Status:** baixa prioridade

**Arquivo:** [api/oferta_enviar.php:76](../api/oferta_enviar.php#L76)

**Problema:** quando IA está ligada e a oferta não tem `mensagem_ia`, o endpoint chama `python gerador.py` síncronamente. O Python carrega config, conecta no banco, chama OpenRouter, escreve no banco. Pode levar 10-30s. Já temos `set_time_limit(120)` mas o proxy do EasyPanel pode cortar antes.

**Correção:** mover a geração para um job separado, ou mostrar mensagem clara de "gerando texto, tente em 30s". Por enquanto está mascarado pelo fallback de status na fila.

---

## Ordem de execução

1. ✅ Fix dedup (commit `e8ebce0`) — feito
2. **#2 — Índices** (rápido, sem risco)
3. **#1 — Lock pessimista** (médio risco, precisa testar fluxo manual + cron)
4. **#3 — Limpeza fila_envio** (opcional)
5. **#4 — Async geração IA** (só se virar problema real)
