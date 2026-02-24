FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# The user will be the same as the host user to avoid permission issues
ARG UID
RUN useradd -G www-data -u ${UID} -d /home/user -s /bin/bash user
RUN mkdir -p /home/user/.composer && \
    chown -R user:user /home/user

USER user
