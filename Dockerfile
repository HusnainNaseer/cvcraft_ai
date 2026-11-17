# CVCraft AI: Laravel + headless Chromium, built for Hugging Face Spaces (Docker SDK).
FROM php:8.2-cli-bookworm

# Chromium renders PDFs and loads job pages that block plain HTTP requests.
RUN apt-get update \
    && apt-get install -y --no-install-recommends chromium fonts-liberation fonts-noto-core libzip-dev libpq-dev unzip \
    && docker-php-ext-install zip pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/cvcraft.ini

# Hugging Face runs containers as user 1000.
RUN useradd --create-home --uid 1000 user
USER user
WORKDIR /home/user/app

COPY --chown=user:user composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=user:user . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x docker/start.sh

# Defaults; secrets (APP_KEY, Gemini and Google keys) come from the Space settings.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    SESSION_DRIVER=database \
    SESSION_SECURE_COOKIE=true \
    SESSION_SAME_SITE=none \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log \
    CHROME_PATH=/usr/bin/chromium \
    CHROME_NO_SANDBOX=true \
    GEMINI_MODELS=gemini-flash-latest,gemini-flash-lite-latest \
    GEMINI_THINKING_LEVEL=low \
    PHP_CLI_SERVER_WORKERS=4

EXPOSE 7860
CMD ["docker/start.sh"]
