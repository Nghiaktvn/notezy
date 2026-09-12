# Standalone service image: a clean production build must not depend on a
# locally named web image or on Docker Compose build ordering.
FROM php:8.2-cli
RUN apt-get update && apt-get install -y --no-install-recommends \
      libcurl4-openssl-dev \
      libonig-dev \
    && docker-php-ext-install pdo_mysql curl mbstring \
    && docker-php-ext-enable pdo_mysql curl mbstring \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY services /app/services
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app/services/gateway/public", "/app/services/gateway/router.php"]
