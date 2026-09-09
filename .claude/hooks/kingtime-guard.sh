#!/bin/bash
# PostToolUse guard (Edit|Write): keeps the CLAUDE.md rules in front of the
# model right after an edit. Informational only, never blocks.
set -u

input=$(cat)
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null) || exit 0
[ -z "$file" ] && exit 0

notes=()

case "$file" in
    */resources/js/types/generated.d.ts|*/resources/js/lib/enums.ts)
        notes+=("GENERATED FILE: $file is written by 'php artisan typescript:transform'. Change the PHP DTO or enum instead and re-run the transform.")
        ;;
    */resources/js/routes/*|*/resources/js/actions/*|*/resources/js/wayfinder/*)
        notes+=("GENERATED FILE: Wayfinder output. Change routes/*.php and run 'php artisan wayfinder:generate --with-form'.")
        ;;
    */app/Models/*|*/app/Http/Controllers/*|*/app/Actions/*|*/app/Services/*)
        notes+=("DDD: new PHP belongs in app/Domain/{Domain}/... (see CLAUDE.md), not in app/ root folders.")
        ;;
esac

case "$file" in
    *.vue|*.ts|*.php|*.md)
        content=$(printf '%s' "$input" | jq -r '(.tool_input.new_string // .tool_input.content) // empty' 2>/dev/null)
        if printf '%s' "$content" | grep -q -- '—'; then
            notes+=("COPY GUARD: the edit adds an em dash. User-facing copy and docs never use em/en dashes (CLAUDE.md); split the sentence or use a comma or middle dot.")
        fi
        if printf '%s' "$content" | grep -qE '\b(fetch\(|axios)'; then
            notes+=("HTTP GUARD: use Inertia's useHttp / router instead of fetch or axios (CLAUDE.md).")
        fi
        ;;
esac

[ ${#notes[@]} -eq 0 ] && exit 0

printf '%s\n' "${notes[@]}" | jq -Rs '{hookSpecificOutput: {hookEventName: "PostToolUse", additionalContext: .}}'
