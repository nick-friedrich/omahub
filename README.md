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

Set `GITHUB_TOKEN` in `.env` to raise GitHub's API rate limit, then import or refresh a public plugin repository with:

```bash
php artisan plugins:import https://github.com/owner/repository
```

The repository must contain a valid Omarchy `manifest.json` at its root. Imports are synchronous and new plugins remain pending until the submission workflow is implemented.

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
