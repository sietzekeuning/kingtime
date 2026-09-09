#!/usr/bin/env bash
# Pull the production database into the local one.
#
# Production runs as the isolated user `kingtime` on the vv-waskemeer Forge
# server; that user has no SSH key, so the dump is made through the Forge
# commands API (bash/forge-command.sh) and fetched over SSH as `forge`, which
# can read the site directory but not the site's .env.
set -euo pipefail

REMOTE_HOST="forge@94.130.59.82"
# Deploy root, not a release dir: it holds the shared .env and survives a deploy.
REMOTE_PATH="/home/kingtime/kingtime.nl"

script_dir=$(cd "$(dirname "$0")" && pwd)
project_dir=$(dirname "$script_dir")

cleanup_remote() {
    bash "$script_dir/forge-command.sh" "rm -f $REMOTE_PATH/db.sql $REMOTE_PATH/db.sql.gz" > /dev/null || true
}
trap cleanup_remote EXIT

# Inline rather than `bash bash/dump.sh`, so this also works before a release
# that contains the script is deployed.
bash "$script_dir/forge-command.sh" "cd $REMOTE_PATH/current && set -a && . ./.env && set +a && mysqldump --no-tablespaces -h \"\$DB_HOST\" -u \"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \"\$DB_DATABASE\" > $REMOTE_PATH/db.sql && cd $REMOTE_PATH && gzip -f db.sql && chmod 644 db.sql.gz && ls -la db.sql.gz"

scp -q "$REMOTE_HOST:$REMOTE_PATH/db.sql.gz" "$project_dir/db.sql.gz"

gzip -d -f "$project_dir/db.sql.gz"

bash "$script_dir/restore.sh"
