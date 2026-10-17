#!/bin/sh
set -eu

mkdir -p /var/www/html/uploads/photos /var/www/html/uploads/documents /var/www/html/uploads/cards /var/www/html/uploads/videos /var/www/html/uploads/gallery
chown -R www-data:www-data /var/www/html/uploads

php /var/www/html/scripts/db_bootstrap.php

if [ "${NCSM_RUN_SEED:-}" = "true" ]; then
	php /var/www/html/seed_cli.php
fi

exec "$@"
