FROM php:8.3-apache

ARG APP_ENV=prod
ENV APP_ENV=${APP_ENV}

# Dépendances système
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libonig-dev libpq-dev libxml2-dev \
    mariadb-client \
    && docker-php-ext-install intl pdo pdo_mysql opcache zip

# Active le module Apache rewrite
RUN a2enmod rewrite headers
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Copie le fichier de configuration Apache personnalisé
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définit le dossier de travail
WORKDIR /var/www/html

# Copie le code source dans le conteneur
COPY . .

# Prépare les permissions et installe les dépendances PHP
RUN mkdir -p var

RUN git config --global --add safe.directory /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-scripts

EXPOSE 80

# Commande de démarrage d'Apache
CMD ["apache2-foreground"]
