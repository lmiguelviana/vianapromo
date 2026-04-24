# Plano de Integração — Magazine Luiza (Magalu)

> Status: **Aguardando cadastro no Parceiro Magalu**
> Criado em: 2026-04-24

---

## Objetivo

Adicionar o Magazine Luiza como segunda fonte de ofertas no sistema Viana Promo, seguindo o mesmo pipeline já funcionando com o Mercado Livre:

**buscar produtos fitness com desconto → gerar link de afiliado → enviar para grupos WhatsApp → exibir no portal público**

---

## Programa de Afiliados

| Item | Detalhe |
|------|---------|
| **Nome** | Parceiro Magalu / Magazine Você |
| **URL de cadastro** | https://www.parceiromagalu.com.br |
| **Custo** | Gratuito |
| **Requisito** | CPF válido (sem CNPJ ou estoque) |
| **Aprovação** | Até 48 horas |
| **Pagamento** | 2x ao mês, mínimo R$50 para sacar |

### Comissões no nicho fitness

| Categoria | Comissão |
|-----------|----------|
| Suplementos / Vitaminas | **17%** |
| Esporte & Lazer | **17%** |
| Roupas / Moda | **19%** |

> ⚠️ As comissões altas (17–19%) se aplicam a produtos **vendidos diretamente pelo Magalu**. Produtos de sellers do marketplace pagam até 3%. O coletor deve priorizar produtos do próprio Magalu.

---

## Como funciona o link de afiliado

O link de afiliado do Magalu é gerado adicionando o parâmetro `smttag` com o ID do parceiro na URL do produto:

```
https://www.magazineluiza.com.br/produto/123456/p/abc123/
  → https://www.magazineluiza.com.br/produto/123456/p/abc123/?smttag=SEU_ID_PARCEIRO
```

O **smttag** é o identificador único gerado no painel do Parceiro Magalu. Ele deve ser salvo no Config do sistema (chave `magalu_smttag`).

---

## Comparação com o pipeline atual (Mercado Livre)

| Etapa | Mercado Livre (atual) | Magalu (planejado) |
|-------|-----------------------|-------------------|
| Buscar produtos | API pública `/products/search` | API pública de catálogo (`developers.magalu.com`) |
| Filtrar desconto | `original_price` vs `price` | Preço de/por na resposta da API |
| Filtrar vendedor ML | `source` do produto | Priorizar `seller_id` = Magalu oficial |
| Link de afiliado | URL + `?partner_id=` | URL + `?smttag=SEU_ID` |
| Imagem | `thumbnail` da API | `thumbnail` da API |
| Badge no sistema | `ML` (laranja) | `MGZ` (azul) — já existe |
| Dedup 48h | `produto_id_externo` no SQLite | Mesmo campo, fonte `MGZ` |
| Blacklist | Mesma tabela | Mesma tabela |

---

## Fases de Implementação

### Fase 1 — Cadastro (você faz antes de implementar)

- [ ] Cadastrar em https://www.parceiromagalu.com.br
- [ ] Aguardar aprovação (até 48h)
- [ ] Pegar o **smttag** (ID de parceiro) no painel
- [ ] Inserir o smttag no Config do painel → campo `magalu_smttag`

### Fase 2 — Bot Python (implementação)

**Arquivo novo:** `bot/coletor_magalu.py`

Responsabilidades:
- Autenticar na API do Magalu (se necessário) ou usar endpoints públicos
- Buscar produtos das categorias fitness com desconto ≥ X%
- Filtrar somente produtos vendidos pelo Magalu direto (comissão alta)
- Gerar link de afiliado com smttag
- Inserir na tabela `ofertas` com `fonte = 'MGZ'`
- Retry com backoff em caso de rate limit (mesmo padrão do coletor ML)

**Palavras-chave iniciais:**
```python
KEYWORDS_MAGALU = [
    'whey protein', 'creatina', 'bcaa', 'pre treino', 'colageno',
    'vitamina c', 'omega 3', 'multivitaminico', 'termogenico',
    'legging fitness', 'shorts academia', 'top fitness', 'camiseta dry fit',
    'tenis academia', 'haltere', 'anilha', 'kettlebell', 'faixa elastica',
    'luva musculacao', 'corda pular',
]
```

**Modificação em** `bot/main.py`:
- Adicionar chamada ao `coletor_magalu.py` no pipeline
- Configurável: pode rodar só ML, só Magalu, ou ambos

### Fase 3 — Painel PHP (implementação)

**`config.php`:**
- Novo campo: `magalu_smttag` — ID do parceiro Magalu
- Toggle para ativar/desativar coleta Magalu

**`app/db.php`:**
- Config key: `magalu_smttag` (default vazio)
- Config key: `magalu_ativo` (default `0`)

**Portal e fila:**
- Badge `MGZ` já existe em `app/helpers.php` (azul)
- Nenhuma mudança necessária no portal — as ofertas já aparecem por `fonte`

---

## APIs e Documentação Técnica

| Recurso | URL |
|---------|-----|
| Portal do desenvolvedor | https://developers.magalu.com |
| Documentação Acelera Magalu | https://acelera.magalu.com |
| Marketplace API (Apiary) | https://magazineluiza.docs.apiary.io |
| Parceiro Magalu (afiliados) | https://www.parceiromagalu.com.br |

---

## Riscos e Observações

| Risco | Mitigação |
|-------|-----------|
| API pode exigir aprovação para acesso | Usar scraping da busca como fallback |
| Produtos de marketplace têm comissão baixa (3%) | Filtrar por `seller_id` = Magalu no coletor |
| Rate limit desconhecido | Implementar delay + retry igual ao ML (2s entre keywords, backoff 60s/120s/180s em 429) |
| smttag inválido gera link sem rastreio | Validar smttag antes de salvar no Config |

---

## Próximo Passo

1. Você se cadastra no **parceiromagalu.com.br** e obtém o smttag
2. Informa o smttag aqui ou no painel
3. Implemento `bot/coletor_magalu.py` + campos no Config em uma sessão
