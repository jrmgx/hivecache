#!/bin/sh
set -e

cd /var/www/api

php bin/console doctrine:database:create -n --if-not-exists
php bin/console doctrine:migrations:migrate -n

exec "$@"
