# Alvo da migração (Fase 2). Erros aqui são INSUMO, não defeito.
FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install mysqli mbstring && a2enmod rewrite
COPY php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini
COPY php-dev-strict.ini /usr/local/etc/php/conf.d/zz-dev-strict.ini
