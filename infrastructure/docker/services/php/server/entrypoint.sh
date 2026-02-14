#!/bin/sh
set -e

cd /var/www/server

php bin/console doctrine:database:create -n --if-not-exists
php bin/console doctrine:migrations:migrate -n

supervisord -c ./config/supervisord.conf

exec "$@"
