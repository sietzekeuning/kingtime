#!/usr/bin/env bash
# Restore db.sql from the project root into the database configured in .env.
set -euo pipefail

cd "$(dirname "$0")/.."
set -a
# shellcheck disable=SC1091
. ./.env
set +a

if [ "${DB_CONNECTION:-mysql}" != "mysql" ]; then
    echo "restore.sh expects DB_CONNECTION=mysql (got ${DB_CONNECTION:-unset})" >&2
    exit 1
fi

mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} "$DB_DATABASE" < db.sql
php artisan migrate --force --no-interaction
echo "Restore DONE"
