# 🏋️ Viana Promo — Automação de Ofertas Fitness

> Sistema criado por **Miguel Viana** para automação de marketing de afiliados fitness via WhatsApp.

---

## 📌 O que é

O **Viana Promo** é uma plataforma completa de automação que:

- 🔍 **Busca** ofertas fitness em promoção no Mercado Livre automaticamente
- 🤖 **Gera** textos de vendas com IA (OpenRouter) ou templates personalizados
- 📱 **Envia** as ofertas para grupos WhatsApp via Evolution API
- 🧠 **Aprende** — produtos rejeitados entram na blacklist e nunca voltam
- 📊 **Painel** administrativo completo para gerenciar tudo

---

## ⚡ Funcionalidades

| Funcionalidade | Descrição |
|---|---|
| 🤖 Pipeline automático | Coleta → Gera texto → Baixa imagem → Envia |
| 📋 Fila de ofertas | Revise, aprove ou rejeite antes de enviar |
| ✉️ Envio manual | Disparo imediato de qualquer oferta com 1 clique |
| 🚫 Blacklist | Produtos rejeitados nunca são coletados novamente |
| 🧪 Teste de IA | Valide sua API Key do OpenRouter diretamente no painel |
| 📈 Histórico | Todos os envios registrados com status |
| 👥 Multi-grupo | Envia para múltiplos grupos WhatsApp simultaneamente |
| 🌐 Logs ao vivo | Terminal no painel atualizado em tempo real |
| ⚙️ Configuração dinâmica | Tudo configurável pelo painel, sem editar arquivos |

---

## 🏗️ Arquitetura

```
Mercado Livre API
      ↓
 coletor.py          → Busca produtos fitness com desconto
      ↓
 gerador.py          → Cria copy de venda (IA ou template)
      ↓
 enriquecedor.py     → Baixa imagem do produto
      ↓
 emissor.py          → Envia para grupos via Evolution API
      ↓
  WhatsApp 📱
```

**Stack:**
- **Backend:** PHP 8.1 + SQLite (PDO)
- **Bot:** Python 3.14+ (`requests`, `openai`)
- **Frontend:** Tailwind CSS + Vanilla JS
- **APIs:** Evolution API · Mercado Livre · OpenRouter

---

## 🚀 Deploy

### Desenvolvimento (Windows + XAMPP)
```
Coloque os arquivos em: C:\xampp\htdocs\viana\
Acesse: http://localhost/viana/
```

### Produção (Ubuntu + EasyPanel)
Consulte o guia completo: [`docs/deploy.md`](docs/deploy.md)

```bash
# Build automático com Dockerfile incluído
# Configure 3 volumes persistentes:
/var/www/viana/database   ← banco SQLite
/var/www/viana/storage    ← logs
/var/www/viana/uploads    ← imagens
```

---

## ⚙️ Configuração

Acesse `/viana/config` e preencha:

- **Evolution API** — URL, API Key, nome da instância
- **Mercado Livre** — Client ID, Client Secret, Partner ID
- **OpenRouter** — API Key (opcional — modo template não precisa)
- **Filtros do bot** — desconto mínimo (%), preço máximo (R$)

---

## 📁 Estrutura

```
viana/
├── bot/              # Pipeline Python (coletor, gerador, emissor...)
├── api/              # Endpoints REST
├── app/              # Core PHP (DB, auth, helpers)
├── docs/             # Documentação técnica
├── assets/           # CSS design system
├── storage/          # Logs e lock file
├── uploads/          # Imagens dos produtos
├── database/         # SQLite (gerado automaticamente)
└── Dockerfile        # Deploy no EasyPanel/Docker
```

---

## 📚 Documentação

| Arquivo | Conteúdo |
|---------|----------|
| [`docs/sistema.md`](docs/sistema.md) | Arquitetura completa, banco, APIs |
| [`docs/deploy.md`](docs/deploy.md) | Deploy no EasyPanel passo a passo |
| [`docs/changelog.md`](docs/changelog.md) | Histórico de versões |
| [`docs/afiliados.md`](docs/afiliados.md) | Guia de programas de afiliados |

---

## 👨‍💻 Autor

**Miguel Viana**  
📧 lmiguelviana@hotmail.com  
🐙 [@lmiguelviana](https://github.com/lmiguelviana)

---

<p align="center">
  Feito com 💚 por Miguel Viana
</p>
