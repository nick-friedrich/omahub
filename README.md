# Omahub

Omahub is a community registry for discovering Omarchy plugins. The first release focuses on accurately importing public GitHub repositories and presenting them in a simple, searchable directory.

See [`plan.md`](plan.md) for the product roadmap.

## Stack

- PHP 8.3+
- Laravel 13
- SQLite
- Blade and Tailwind CSS
- Vite

## Local setup

Install PHP, Composer, Node.js, and the PHP SQLite extension, then run:

```bash
git clone <repository-url> omahub
cd omahub
cp .env.example .env
composer run setup
```

`composer run setup` installs dependencies, creates the SQLite database, runs migrations, and builds frontend assets.

To add development data:

```bash
php artisan db:seed
```

Set `GITHUB_TOKEN` in `.env` to raise GitHub's API rate limit, then import or refresh a public plugin repository directly from the command line (admin only):

```bash
php artisan plugins:import https://github.com/owner/repository
```

The repository must contain a valid Omarchy `manifest.json` at its root. Imports are synchronous and new plugins start as pending.

To re-visit and update plugins already in the registry (refreshing GitHub metadata and fetching READMEs), run:

```bash
php artisan plugins:refresh                 # refresh every plugin
php artisan plugins:refresh --missing-readme # only plugins without a README
php artisan plugins:refresh --ids=1,2,3     # refresh specific plugins
php artisan plugins:refresh --after=108     # resume a stopped run from plugin ID 108
php artisan plugins:refresh --limit=50      # cap the number refreshed
php artisan plugins:refresh --dry-run       # list targets without calling GitHub
```

Refresh is idempotent: re-importing a repository updates the existing plugin in place and preserves its status. If a repository has moved, the existing row is updated to its new owner/name instead of creating a duplicate. Failures (repos without a root `manifest.json`, or moved or deleted repositories) are reported per plugin and the run continues; only a GitHub API rate limit stops the run early, with the resume command printed. The importer fetches the README from the repository's default branch. Set `GITHUB_TOKEN` in `.env` (e.g. `echo "GITHUB_TOKEN=$(gh auth token)" >> .env`) to avoid the unauthenticated rate limit when refreshing many plugins at once.

## Authentication (GitHub OAuth)

Sign-in is required to submit a plugin, which keeps the registry spam-free. Configuring it requires a GitHub OAuth App:

1. Create an OAuth App at <https://github.com/settings/developers> (Homepage URL: `APP_URL`, Authorization callback URL: `<APP_URL>/auth/github/callback`).
2. Copy its Client ID and Client Secret into `.env`:

```bash
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=
GITHUB_ADMIN_USERNAMES=
```

`GITHUB_REDIRECT_URI` defaults to `<APP_URL>/auth/github/callback`; set it explicitly only to override. `GITHUB_ADMIN_USERNAMES` is a comma-separated list of GitHub logins granted admin access when they sign in (e.g. `GITHUB_ADMIN_USERNAMES=nick,sarah`). Admins can also be managed later with `php artisan user:admin --username=...` / `php artisan user:admin --username=... --remove`.

Without these values, the “Sign in with GitHub” button shows a config notice in `.env` and sign-in is unavailable.

## Submissions

Visitors can submit a plugin by pasting a public GitHub repository URL at `GET /submit`. Submitting requires a signed-in GitHub account. The site form is rate limited and includes a honeypot for bots. Each submission runs the importer synchronously, records the outcome, and stays **pending** for a maintainer to review — nothing is published automatically.

## Admin interface

Signing in with a GitHub login listed in `GITHUB_ADMIN_USERNAMES` (or granted via `php artisan user:admin`) exposes the admin area at `GET /admin`. From there you can:

- **Submissions** — review pending submissions, approve (which publishes the imported plugin), or reject with an optional reason.
- **Plugins** — list all listings (filtered by status), edit metadata (name, description, author, license, homepage) and categories/tags, re-import a single plugin from GitHub, change its status (publish/archive/reject/pending), or delete it.

The admin routes are web forms (no API). They're guarded by an `admin` middleware that requires an authenticated user with `is_admin = true`.

## Reviewing submissions from the CLI

Pending submissions can also be reviewed from the terminal:

```bash
php artisan submissions:list                      # show submissions awaiting review
php artisan submissions:approve 12                # publish the linked plugin
php artisan submissions:reject 12 "reason"        # reject and settle the plugin
php artisan submissions:list --all                # include reviewed/failed submissions
```

Approving a submission publishes its plugin (sets `status` to `published`); rejecting a pending submission leaves the plugin unpublished (or rejected if it was still pending).

Start the application and asset watcher:

```bash
composer run dev
```

The application is available at <http://localhost:8000>.

## Common commands

```bash
php artisan migrate:fresh --seed  # Rebuild the development database
composer test                     # Run the test suite
./vendor/bin/pint                 # Format PHP code
npm run build                     # Build frontend assets
```

## Architecture

Omahub begins as a conventional Laravel monolith. Public pages are server-rendered with Blade and call application services directly. SQLite is the default database, queues run synchronously, and sessions and cache use the filesystem. Additional infrastructure should only be introduced in response to a measured need.
