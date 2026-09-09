# Kingtime

Open-source time tracking for freelancers and small studios. Log hours per client, project and task, import your history from Harvest, prepare invoices as Moneybird drafts with a clean hour specification, and let your LLM do the bookkeeping through the built-in MCP server.

Built with Laravel 13, Inertia v3, Vue 3, shadcn-vue and Tailwind v4. Live at [kingtime.nl](https://kingtime.nl).

## Features

- **Timesheet** with a Monday to Sunday week strip, per-day totals, a running timer that is visible on every page, and a filterable table of every entry ever logged.
- **Clients, projects and tasks** with per-project or per-task hourly rates, budgets and colours, the way Harvest models them.
- **Harvest import**: one command (or a button on the integrations page) pulls users, clients, projects, tasks, task assignments and all time entries. Runs are idempotent and incremental, and can be scheduled hourly.
- **Invoicing through Moneybird**: pick a client and a period, review the unbilled hours grouped per project and task, and create a draft sales invoice in Moneybird with the full specification attached as a note. Statuses sync back daily.
- **MCP server**: connect Claude Desktop, Claude Code, Cursor or any MCP client with a personal API token and say "log two hours of development on the Acme website for today" or "prepare the September invoice for Globex".
- **Dashboard** with hours today, this week and this month against the previous period, a weekly hours chart, billable ratio and top projects.
- Passkeys and two-factor authentication out of the box (Laravel Fortify). Sign-up closes automatically after the first user.

## Requirements

- PHP 8.4 or newer, Composer
- Node 22 or newer, npm
- SQLite (default) or MySQL/MariaDB/PostgreSQL

## Installation

```bash
git clone https://github.com/sietzekeuning/kingtime.git
cd kingtime
composer run setup      # composer install, .env, key, migrations, npm install, npm run build
composer run dev        # php artisan dev: server, queue, logs and Vite in one terminal
```

Open the app, register the first account (registration closes afterwards), and you are in. Want demo data to look at first? Run `php artisan db:seed` for a demo user (`demo@kingtime.test` / `password`) with six months of entries.

## Harvest import

1. Create a personal access token at <https://id.getharvest.com/developers> and put it in `.env`:

    ```dotenv
    HARVEST_ACCOUNT_ID=123456
    HARVEST_ACCESS_TOKEN=your-token
    ```

2. Run the import:

    ```bash
    php artisan harvest:import --full     # everything, the first time
    php artisan harvest:import            # only what changed since the last run
    php artisan harvest:import --since=2026-01-01
    ```

Every row keeps its `harvest_id`, so re-running never duplicates anything and local edits to fields Harvest does not know about (project colours, Moneybird contact ids, notes) survive. The scheduler runs the incremental import every hour once the token is configured; Settings › Integrations shows the last runs and has a "Run import now" button.

## Moneybird invoicing

1. Create an API token at <https://moneybird.com/user/applications> and add it together with your administration id:

    ```dotenv
    MONEYBIRD_ACCESS_TOKEN=your-token
    MONEYBIRD_ADMINISTRATION_ID=123456789
    # optional, applied to every invoice line
    MONEYBIRD_TAX_RATE_ID=
    MONEYBIRD_LEDGER_ACCOUNT_ID=
    MONEYBIRD_WORKFLOW_ID=
    ```

2. Go to Invoices › Prepare invoice, pick a client and a period, uncheck any entries you want to leave out, and create the draft. With "Push to Moneybird" on, the contact is looked up (or created) by name and a **draft** sales invoice is created with one line per project and task and the specification (hours and notes per day) as a note. Nothing is ever sent to your customer from Kingtime; you review and send in Moneybird.

Invoiced entries are locked. Deleting a draft that was not pushed unlocks them again. `php artisan invoices:sync-statuses` (scheduled daily) pulls the paid/late/open state back from Moneybird.

## Connect your LLM (MCP)

Kingtime ships an MCP server at `/mcp`, authenticated with a personal API token (Settings › API tokens).

```bash
claude mcp add --transport http kingtime https://kingtime.nl/mcp \
  --header "Authorization: Bearer <your-token>"
```

Claude Desktop, Cursor and friends take the same thing as JSON:

```json
{
    "mcpServers": {
        "kingtime": {
            "url": "https://kingtime.nl/mcp",
            "headers": { "Authorization": "Bearer <your-token>" }
        }
    }
}
```

For a local install without HTTPS you can also run it over stdio: `php artisan mcp:start kingtime` (acts as the first user in the database).

Tools: `list_clients`, `list_projects`, `list_tasks`, `list_time_entries`, `get_timesheet`, `log_time`, `update_time_entry`, `delete_time_entry`, `start_timer`, `stop_timer`, `get_running_timer`, `get_unbilled_summary`, `preview_invoice` and `prepare_invoice`. Invoices prepared through MCP are drafts, exactly like the ones from the UI.

## Development

```bash
composer run dev                       # app + queue + logs + Vite
./vendor/bin/pest                      # tests (SQLite in memory, no build needed)
vendor/bin/pint --dirty                # PHP formatting
vendor/bin/phpstan analyse --memory-limit=2G
npx vp check --fix && npx vue-tsc --noEmit
php artisan typescript:transform       # regenerate resources/js/types/generated.d.ts + lib/enums.ts
php artisan wayfinder:generate --with-form
```

How the code is organised:

- `app/Domain/{Client,Project,Time,Invoice,Harvest,Moneybird,Mcp,Dashboard,User,Shared}`: models, DTOs (Spatie Laravel Data), tables, actions, controllers per domain.
- DTOs are the source of truth for the frontend types: `php artisan typescript:transform` writes `resources/js/types/generated.d.ts` and `resources/js/lib/enums.ts`, so Vue pages are fully typed end to end.
- List pages are one `Table` class (sorting, filtering) plus the `DataTable` component.
- `CLAUDE.md` documents the conventions for contributors and coding agents.

## Deploying

The project deploys with [Laravel Forge](https://forge.laravel.com) using zero-downtime deployments: GitHub Actions builds the assets and runs the test suite, then force-pushes a `deploy` branch that Forge picks up. See `.github/workflows/production.yml`. Any host that runs Laravel works; build the assets with `npm run build` and run the scheduler (`php artisan schedule:run` every minute) plus a queue worker for the Harvest import job.

## License

MIT. See [LICENSE](LICENSE).
