#!/bin/sh
set -e

cd /var/www/html

PORT="${PORT:-80}"
sed "s/__PORT__/${PORT}/g" /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
fi

if [ -n "${IA_DIAGNOSTIC_HOSTPORT:-}" ]; then
    export IA_DIAGNOSTIC_URL="http://${IA_DIAGNOSTIC_HOSTPORT}"
fi

# L'offre gratuite Render bloque les ports SMTP. Le relais part en HTTPS.
if [ -n "${MAIL_RELAY_URL:-}" ]; then
    export MAIL_MAILER=relais
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY absente : le conteneur ne demarre pas sans cle Laravel." >&2
    exit 1
fi

php artisan storage:link >/dev/null 2>&1 || true

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
