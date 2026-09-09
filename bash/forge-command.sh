#!/usr/bin/env bash
# Run a shell command on the production site through the Forge API and print
# its output. The site runs as the isolated user `kingtime`, which has no SSH
# key, so this is the only way to execute something as that user.
#
#   bash bash/forge-command.sh 'php artisan backup:run'
#
# Needs FORGE_API_TOKEN (a Forge API v2 token) in the environment or .envrc.
set -euo pipefail

ORG=king-websites
SERVER=892926
SITE=3372859
BASE="https://forge.laravel.com/api/orgs/$ORG/servers/$SERVER/sites/$SITE"

if [ -z "${FORGE_API_TOKEN:-}" ] && [ -f "$(dirname "$0")/../.envrc" ]; then
    # shellcheck disable=SC1091
    . "$(dirname "$0")/../.envrc"
fi
: "${FORGE_API_TOKEN:?FORGE_API_TOKEN is not set}"

auth=(-H "Authorization: Bearer $FORGE_API_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json")

python3 -c 'import json,sys; print(json.dumps({"command": sys.argv[1]}))' "$1" \
    | curl -sf "${auth[@]}" -X POST "$BASE/commands" -d @- > /dev/null

# The POST returns no id. The list is cursor-paginated, oldest first, so
# follow the cursors to the last page and take the newest command there.
newest_command() {
    python3 - "$BASE/commands" "$FORGE_API_TOKEN" <<'PY'
import json, sys, urllib.request
url, token = sys.argv[1], sys.argv[2]
last = None
while url:
    req = urllib.request.Request(url, headers={"Authorization": f"Bearer {token}", "Accept": "application/json", "User-Agent": "curl/8.0"})
    with urllib.request.urlopen(req) as r:
        d = json.load(r)
    if d.get("data"):
        last = d["data"][-1]
    url = (d.get("links") or {}).get("next")
print(last["id"], last["attributes"].get("status"))
PY
}

for _ in $(seq 1 60); do
    sleep 5
    read -r id status < <(newest_command)
    case "$status" in
        finished|failed)
            curl -sf "${auth[@]}" "$BASE/commands/$id/output" | python3 -c '
import json,sys
d = json.load(sys.stdin); a = d.get("data", d)
print((a.get("attributes", a).get("output") or "").rstrip())'
            [ "$status" = finished ]
            exit
            ;;
    esac
done

echo "Timed out waiting for the Forge command" >&2
exit 1
