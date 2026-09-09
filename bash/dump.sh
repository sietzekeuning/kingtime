#!/usr/bin/env bash
# Dump the database configured in .env to db.sql in the project root.
# Runs on the production box (as the site user) and locally.
set -euo pipefail

cd "$(dirname "$0")/.."
set -a
# shellcheck disable=SC1091
. ./.env
set +a

if [ "${DB_CONNECTION:-mysql}" != "mysql" ]; then
    echo "dump.sh expects DB_CONNECTION=mysql (got ${DB_CONNECTION:-unset})" >&2
    exit 1
fi

mysqldump --no-tablespaces -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} "$DB_DATABASE" > db.sql
echo "Dump DONE ($(du -h db.sql | cut -f1))"
