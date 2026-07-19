#!/bin/sh
set -e

composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan config:clear

exec php artisan serve --host=0.0.0.0 --port=8000
