#!/usr/bin/env sh
set -eo pipefail

WS_UID=${WS_UID:-1000}
WS_GID=${WS_GID:-1000}
WS_NO_CHOWN=${WS_NO_CHOWN:-0}
WS_DISABLE_HTTP=${WS_DISABLE_HTTP:-0}
WS_CRON_IMPORT=${WS_CRON_IMPORT:-0}
WS_CRON_PUSH=${WS_CRON_PUSH:-0}
WS_CRON_EXPORT=${WS_CRON_EXPORT:-0}

set -u

if [ "${WS_UID}" != "$(id -u www-data)" ]; then
  usermod -u ${WS_UID} www-data
fi

if [ "${WS_GID}" != "$(id -g www-data)" ]; then
  groupmod -g ${WS_GID} www-data
fi

if [ ! -f "/app/vendor/autoload.php" ]; then
  if [ ! -f "/usr/bin/composer" ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
  fi
  runuser -u www-data -- composer --ansi --working-dir=/app/ -o --no-progress --no-cache install
fi

if [ ! -f "/usr/bin/console" ]; then
  cp /app/docker/files/app_console.sh /usr/bin/console
  chmod +x /usr/bin/console
fi

if [ ! -f "/usr/bin/run-app-cron" ]; then
  cp /app/docker/files/cron.sh /usr/bin/run-app-cron
  chmod +x /usr/bin/run-app-cron
fi

if [ 0 == "${WS_NO_CHOWN}" ]; then
  chown -R www-data:www-data /config
fi

/usr/bin/console config:php >"${PHP_INI_DIR}/conf.d/zz-app-custom-ini-settings.ini"
/usr/bin/console config:php --fpm >"${PHP_INI_DIR}/../php-fpm.d/zzz-app-pool-settings.conf"
/usr/bin/console storage:migrations
/usr/bin/console storage:maintenance

if [ 0 == "${WS_DISABLE_HTTP}" ] && [ -f "/etc/caddy/Caddyfile" ]; then
  echo "Starting Caddy server.."
  caddy start -config /etc/caddy/Caddyfile
fi

if [ "1" == "${WS_CRON_IMPORT}" ] || [ "1" == "${WS_CRON_EXPORT}" ] || [ "1" == "${WS_CRON_PUSH}" ]; then
  echo "Starting cron..."
  /usr/sbin/crond -b -l 2
fi

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
  set -- php-fpm "$@"
fi

exec "$@"
