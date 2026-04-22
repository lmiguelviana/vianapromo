# Deploy no EasyPanel — Passo a Passo

## O que você vai precisar
- Conta no GitHub (gratuita)
- Acesso ao EasyPanel da sua VPS
- Os arquivos do Viana Promo (já estão prontos)

---

## PARTE 1 — Subir o projeto para o GitHub

### 1.1 Criar repositório no GitHub
1. Acesse **github.com** → clique em **"New repository"**
2. Nome: `viana-promo` (ou qualquer nome)
3. **Privado** (recomendado — tem suas API Keys)
4. Clique em **"Create repository"**

### 1.2 Subir os arquivos pelo terminal
Abra o PowerShell **dentro da pasta do projeto** e execute:

```powershell
cd C:\xampp\htdocs\viana

# Inicializa o Git (se ainda não tiver)
git init
git add .
git commit -m "Viana Promo - deploy inicial"

# Conecta ao seu repositório (substitua SEU_USUARIO pelo seu usuário do GitHub)
git remote add origin https://github.com/SEU_USUARIO/viana-promo.git
git branch -M main
git push -u origin main
```

> ✅ Agora seu código está no GitHub

---

## PARTE 2 — Configurar no EasyPanel

### 2.1 Criar novo serviço
1. Acesse seu EasyPanel: `http://SEU_IP_VPS:3000`
2. Clique em **"+ Create Service"**
3. Escolha **"App"**
4. Nome: `viana-promo`

### 2.2 Conectar o GitHub
1. Na aba **"Source"**, selecione **"GitHub"**
2. Clique em **"Connect GitHub"** e autorize
3. Selecione o repositório `viana-promo`
4. Branch: `main`
5. Build method: **"Dockerfile"** ← importante!

### 2.3 Configurar a porta
1. Na aba **"Domains"**:
   - Porta: `80`
   - Adicione seu domínio (ex: `promo.seusite.com.br`)
   - Ative **"HTTPS / Let's Encrypt"** para SSL grátis

### 2.4 Volumes persistentes (CRÍTICO)
Sem isso, o banco de dados é apagado a cada deploy!

Na aba **"Mounts"**, adicione 3 volumes:

| Mount Path (container) | Descrição |
|------------------------|-----------|
| `/var/www/viana/database` | Banco SQLite |
| `/var/www/viana/storage` | Logs e lock file |
| `/var/www/viana/uploads` | Imagens dos produtos |

### 2.5 Deploy!
1. Clique em **"Deploy"**
2. Aguarde o build (3-5 minutos na primeira vez)
3. Quando aparecer **"Running"** em verde → está no ar! ✅

---

## PARTE 3 — Configurar variáveis de ambiente (antes de implantar)

Ao invés de configurar manualmente no painel web, você pode injetar tudo pelo **EasyPanel → Ambiente** antes do primeiro deploy. O sistema popula o banco automaticamente.

### 3.1 Copiar e colar no EasyPanel → Ambiente

Cole o bloco abaixo e substitua pelos seus valores:

```
EVOLUTION_URL=https://evolution.brasilvibecoding.com.br
EVOLUTION_APIKEY=us3LW70oFDZXl4vHK3lIjz1u5oHzaOj2
EVOLUTION_INSTANCE=Achados
ML_CLIENT_ID=4548872534312733
ML_CLIENT_SECRET=sxTCGSQFotRoiz8v6fk7wYqDUuD7UIr6
ML_PARTNER_ID=52517473
OPENROUTER_APIKEY=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
OPENROUTER_MODEL=moonshotai/moonlight-16b-a3b-instruct:free
APP_BASE=
```

> ⚠️ `APP_BASE=` deve ficar **vazio** — é o que faz o app funcionar na raiz `/` da VPS.

### Como funciona o seed

Ao acessar qualquer página pela primeira vez, `app/db.php` verifica cada variável de ambiente. Se o campo no banco estiver vazio **e** a variável estiver definida, o valor é gravado automaticamente.

| Variável | Chave no banco | Comportamento |
|----------|---------------|---------------|
| `EVOLUTION_URL` | `evolution_url` | Sobrescreve apenas se vazio |
| `EVOLUTION_APIKEY` | `evolution_apikey` | Sobrescreve apenas se vazio |
| `EVOLUTION_INSTANCE` | `evolution_instance` | Sobrescreve apenas se vazio |
| `ML_CLIENT_ID` | `ml_client_id` | Sobrescreve apenas se vazio |
| `ML_CLIENT_SECRET` | `ml_client_secret` | Sobrescreve apenas se vazio |
| `ML_PARTNER_ID` | `ml_partner_id` | Sobrescreve apenas se vazio |
| `OPENROUTER_APIKEY` | `openrouter_apikey` | Sobrescreve apenas se vazio |
| `OPENROUTER_MODEL` | `openrouter_model` | Sobrescreve apenas se vazio |
| `APP_BASE` | *(URL base PHP)* | Vazio = raiz `/` |

> 💡 As variáveis **nunca sobrescrevem** um valor já salvo no banco. Se quiser forçar uma atualização, edite direto em **Config** no painel web.

### 3.2 Acessar o painel
Após o deploy: `https://SEU_DOMINIO/`  
Login: `admin` / `marley123`

### 3.3 Testar
1. Vá em **Grupos** → adicione seu grupo WhatsApp
2. Clique **"Rodar Bot Agora"** na Fila de Ofertas
3. Acompanhe em **Logs do Sistema**

---

## PARTE 4 — Atualizar o sistema no futuro

Sempre que quiser atualizar, só precisa:

```powershell
cd C:\xampp\htdocs\viana
git add .
git commit -m "descrição da mudança"
git push
```

No EasyPanel → clique em **"Redeploy"** → pronto! O banco e uploads são preservados (volumes).

---

## Problemas Comuns

| Erro | Solução |
|------|---------|
| Página em branco / 500 | Verificar logs no EasyPanel → "Logs" tab |
| 404 em todas as páginas | `APP_BASE` não está vazio — verifique a variável de ambiente |
| Bot não roda | Verificar se o container tem `python3`: EasyPanel → Terminal → `python3 --version` |
| Banco zerado após deploy | Volume `/database` não foi configurado — adicione em Mounts |
| Config não aparece preenchida | Variáveis de ambiente só populam o banco se o campo estiver vazio |
| URL não funciona | Verificar se a porta 80 está exposta e o domínio está apontando para a VPS |
| Sem HTTPS | Ativar Let's Encrypt na aba Domains |
