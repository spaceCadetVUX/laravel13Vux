#!/bin/bash
set -e

cd /backend

echo "==> Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Linking storage..."
php artisan storage:link --force

echo "==> Caching config..."
php artisan config:cache

echo "==> Starting PHP-FPM..."
exec php-fpm
