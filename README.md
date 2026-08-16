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

## Submissions

Visitors can submit a plugin by pasting a public GitHub repository URL at `GET /submit`. The site form is rate limited and includes a honeypot for bots. Each submission runs the importer synchronously, records the outcome, and stays **pending** for a maintainer to review — nothing is published automatically.

Review pending submissions as the admin (artisan is the backend interface for maintaining the database):

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
