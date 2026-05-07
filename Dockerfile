FROM php:8.4-cli

# Instalar dependências de sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar o diretório de trabalho
WORKDIR /app

# O comando padrão para manter o container rodando
CMD ["tail", "-f", "/dev/null"]
