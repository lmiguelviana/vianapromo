#!/bin/bash
# =============================================================================
# deploy.sh — Deploy do Viana Promo em Ubuntu + EasyPanel
# =============================================================================
# Uso:
#   chmod +x deploy.sh
#   sudo ./deploy.sh
# =============================================================================

set -e  # Para na primeira falha

VIANA_DIR="/var/www/viana"
PHP_USER="www-data"

echo ""
echo "============================================="
echo "  VIANA PROMO — Deploy Ubuntu + EasyPanel"
echo "============================================="
echo ""

# ── 1. Dependências do sistema ────────────────────────────────────────────────
echo "[1/6] Instalando dependências do sistema..."
apt-get update -qq
apt-get install -y -qq python3 python3-pip php php-sqlite3 php-mbstring php-curl apache2

# Habilita mod_rewrite (para URLs limpas do painel)
a2enmod rewrite
echo "      ✅ Dependências OK"

# ── 2. Dependências Python ────────────────────────────────────────────────────
echo "[2/6] Instalando dependências Python..."
pip3 install -q requests openai
echo "      ✅ Python OK"

# ── 3. Permissões de pasta ────────────────────────────────────────────────────
echo "[3/6] Configurando permissões..."
mkdir -p "$VIANA_DIR/storage"
mkdir -p "$VIANA_DIR/uploads"
mkdir -p "$VIANA_DIR/database"

chown -R "$PHP_USER:$PHP_USER" "$VIANA_DIR/storage"
chown -R "$PHP_USER:$PHP_USER" "$VIANA_DIR/uploads"
chown -R "$PHP_USER:$PHP_USER" "$VIANA_DIR/database"

chmod 755 "$VIANA_DIR/storage"
chmod 755 "$VIANA_DIR/uploads"
chmod 755 "$VIANA_DIR/database"
echo "      ✅ Permissões OK"

# ── 4. Apache Virtual Host ────────────────────────────────────────────────────
echo "[4/6] Configurando Apache Virtual Host..."
cat > /etc/apache2/sites-available/viana.conf <<EOF
<VirtualHost *:80>
    ServerName viana.local
    DocumentRoot $VIANA_DIR

    <Directory $VIANA_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/viana_error.log
    CustomLog \${APACHE_LOG_DIR}/viana_access.log combined
</VirtualHost>
EOF

a2ensite viana.conf
systemctl reload apache2
echo "      ✅ Apache OK"

# ── 5. Cron Job (a cada 6 horas) ─────────────────────────────────────────────
echo "[5/6] Configurando Cron Job..."
PYTHON_PATH=$(which python3)
CRON_JOB="0 */6 * * * $PHP_USER $PYTHON_PATH $VIANA_DIR/bot/main.py >> /dev/null 2>&1"
CRON_FILE="/etc/cron.d/viana-promo"

echo "$CRON_JOB" > "$CRON_FILE"
chmod 644 "$CRON_FILE"
echo "      ✅ Cron criado: roda a cada 6h como $PHP_USER"

# ── 6. Verificação final ──────────────────────────────────────────────────────
echo "[6/6] Verificação final..."
python3 --version
php --version | head -1
apache2 -v | head -1

echo ""
echo "============================================="
echo "  ✅ Deploy concluído!"
echo "============================================="
echo ""
echo "  Próximos passos:"
echo "  1. Configure as variáveis em: http://SEU_IP/viana/config"
echo "     - Evolution API URL, Key, Instance"
echo "     - Mercado Livre Client ID/Secret"
echo "  2. Acesse: http://SEU_IP/viana/"
echo "  3. Login: admin / viana2024"
echo ""
echo "  Cron ativo: $CRON_FILE"
echo "  Logs do bot: $VIANA_DIR/storage/bot.log"
echo ""
