# Viana Promo — Instruções para o Agente

## LEIA SEMPRE ANTES DE QUALQUER AÇÃO

Antes de responder ou modificar qualquer arquivo deste projeto, leia obrigatoriamente:

- [.agent/projeto.md](.agent/projeto.md) — contexto completo do projeto, stack, estrutura, padrões e decisões

Se existirem outros arquivos em `.agent/`, leia todos eles também.

---

## 🔴 REGRA CRÍTICA — DOCUMENTAÇÃO OBRIGATÓRIA

> **APÓS QUALQUER ALTERAÇÃO NO PROJETO, ATUALIZAR OS DOCS É OBRIGATÓRIO.**

### O que deve ser atualizado e quando:

| Tipo de alteração | O que atualizar |
|-------------------|----------------|
| Nova página criada | `.agent/projeto.md` → seção "Estrutura de arquivos" |
| Nova tabela no banco | `.agent/projeto.md` → seção "Banco de dados" |
| Nova API criada | `.agent/projeto.md` → seção "APIs REST" |
| Mudança de stack/tecnologia | `.agent/projeto.md` → seção "Stack" |
| Novo módulo Python (bot/) | `.agent/projeto.md` + `docs/sistema.md` |
| Mudança de design/paleta | `.agent/projeto.md` → seção "Design System" |
| Nova funcionalidade grande | `docs/sistema.md` → seção correspondente |
| Novo plano ou fase | Arquivo de plano em `docs/` |

### Checklist pós-implementação (SEMPRE executar):

```
[ ] .agent/projeto.md atualizado?
[ ] docs/sistema.md reflete as mudanças?
[ ] Algum plano em docs/ precisa ser revisado?
```

**Não marcar tarefa como concluída sem atualizar os docs.**

---

## Regras de trabalho neste projeto

1. **Sempre consulte `.agent/` primeiro** — não assuma nada sobre a estrutura sem ler o contexto
2. **Mantenha os padrões existentes** — APIs retornam `{ ok, ... }` via `jsonResponse()`, páginas usam `layoutStart/layoutEnd`, modais seguem o padrão CSS de `assets/app.css`
3. **Não crie dependências externas** — sem Composer, sem npm, sem jQuery. Stack é PHP puro + Tailwind CDN + JS nativo (exceto bot Python em `bot/`)
4. **SQLite é o banco** — use sempre `getDB()` de `app/db.php`, nunca conecte diretamente
5. **Design System** — cor primária é Emerald (`emerald-600`). NUNCA usar indigo/roxo (Purple Ban)
6. **Após cada implementação** → atualizar `docs/sistema.md` e `.agent/projeto.md`

## Quando o usuário pedir algo novo

1. Leia `.agent/projeto.md`
2. Verifique se o arquivo relevante já existe antes de criar um novo
3. Siga os padrões de código do projeto
4. Implemente
5. **Atualize `.agent/projeto.md` e `docs/sistema.md`** — obrigatório

## Docs do projeto

| Arquivo | Conteúdo |
|---------|----------|
| `.agent/projeto.md` | Contexto técnico completo, stack, estrutura, padrões e regras críticas |
| `docs/sistema.md` | Documentação do sistema para referência humana (arquitetura, pipeline, APIs) |
| `docs/changelog.md` | Histórico de mudanças por sessão (bugs corrigidos, funcionalidades novas) |
| `docs/afiliados.md` | Guia de programas de afiliados por plataforma |
| `docs/plano-viana-promo-automatico.md` | Plano da plataforma automática (bot Python) |
