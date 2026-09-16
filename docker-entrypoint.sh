#!/bin/sh
set -eu

mkdir -p /var/www/html/uploads/photos /var/www/html/uploads/documents /var/www/html/uploads/cards /var/www/html/uploads/videos /var/www/html/uploads/gallery
chown -R www-data:www-data /var/www/html/uploads

exec "$@"
