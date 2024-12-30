# Utiliser l'image officielle PHP 8.1 avec extensions nécessaires
FROM php:8.2-cli

# Installer les extensions nécessaires pour Symfony et PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /app

# Copier le contenu du projet dans le conteneur
COPY . .

# Exposer le port pour Symfony
EXPOSE 8000
