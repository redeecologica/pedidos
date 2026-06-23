# Referência comportamental (produção roda 5.3.29; imagem oficial mais antiga é 5.6;
# nada que o app usa mudou entre 5.4 e 5.6 — produção segue sendo a referência real).
# NÃO usar apt-get aqui: repositórios do Debian desta imagem foram arquivados.
FROM php:5.6-apache
RUN docker-php-ext-install mysqli mbstring && a2enmod rewrite
COPY php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini
