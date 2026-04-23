FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
# APP_BASE vazio = app na raiz / (producao). Em XAMPP local usa '/viana'
ENV APP_BASE=""

# ── Dependências ─────────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-sqlite3 \
    php8.1-mbstring \
    php8.1-curl \
    php8.1-bcmath \
    libapache2-mod-php8.1 \
    python3 \
    python3-pip \
    cron \
    && rm -rf /var/lib/apt/lists/*

RUN pip3 install --no-cache-dir requests openai

# ── Apache: habilita mod_rewrite ─────────────────────────────────────────────
RUN a2enmod rewrite

# ── Copia o projeto e usa .htaccess de producao ─────────────────────────────
COPY . /var/www/viana/
COPY .htaccess.production /var/www/viana/.htaccess

# ── Permissões ───────────────────────────────────────────────────────────────
RUN mkdir -p /var/www/viana/storage \
             /var/www/viana/uploads \
             /var/www/viana/database \
    && chown -R www-data:www-data /var/www/viana \
    && chmod -R 755 /var/www/viana/storage \
    && chmod -R 755 /var/www/viana/uploads \
    && chmod -R 755 /var/www/viana/database

# ── Virtual Host Apache ──────────────────────────────────────────────────────
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/viana\n\
    <Directory /var/www/viana>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes +FollowSymLinks\n\
    </Directory>\n\
    # Bloqueia acesso direto ao banco e logs\n\
    <LocationMatch "^/(database|storage|bot)/">\n\
        Require all denied\n\
    </LocationMatch>\n\
</VirtualHost>' > /etc/apache2/sites-available/viana.conf \
    && a2ensite viana.conf \
    && a2dissite 000-default.conf

# ── Cron: verifica a cada 30 min se deve rodar o bot (controlado pelo painel) ─
RUN echo "*/30 * * * * www-data php /var/www/viana/cron/bot_cron.php >> /dev/null 2>&1" \
    > /etc/cron.d/viana-promo \
    && chmod 644 /etc/cron.d/viana-promo

# ── Script de inicialização (Apache + Cron juntos) ────────────────────────────
RUN printf '#!/bin/bash\nservice cron start\napache2ctl -D FOREGROUND\n' \
    > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
