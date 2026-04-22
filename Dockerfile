FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data

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

# ── Copia o projeto ──────────────────────────────────────────────────────────
COPY . /var/www/viana/

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

# ── Cron: bot a cada 6 horas ─────────────────────────────────────────────────
RUN echo "0 */6 * * * www-data python3 /var/www/viana/bot/main.py >> /dev/null 2>&1" \
    > /etc/cron.d/viana-promo \
    && chmod 644 /etc/cron.d/viana-promo

# ── Script de inicialização (Apache + Cron juntos) ────────────────────────────
RUN echo '#!/bin/bash\nservice cron start\napache2ctl -D FOREGROUND' \
    > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
