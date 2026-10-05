FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev unzip git \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Moindre privilège : le serveur ne tourne pas en root.
RUN useradd --create-home app
WORKDIR /app
USER app
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
