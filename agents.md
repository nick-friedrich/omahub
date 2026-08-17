# agents.md — deployment & operating notes for agents

Short guide for running this app in production. This is a **production** deployment —
prefer the deploy script, and treat anything destructive with care.

## Architecture (how it's served)

- A Docker **Caddy reverse proxy** (`reverse-proxy-caddy-1`) fronts `omahub.dev` and
  `www.omahub.dev`. It does `php_fastcgi fpm:9000` → the shared php-fpm container
  (`reverse-proxy-fpm-1`) and serves `public/` as static files.
- Both containers **bind-mount this repo directory directly**:
  - Caddy mounts `<repo>/public` read-only at `/srv/omahub/public`.
  - The fpm container mounts the whole `<repo>` read-write at `/srv/omahub`.
- Therefore **the repo itself IS the deployment** — there is no separate build/upload
  step. "Deploy" = pull new code + rebuild frontend assets + refresh Laravel caches.
- No queue workers or scheduler run in production (`QUEUE_CONNECTION=sync`, no cron).
- The host has **no `php`/`composer` binary** — artisan + composer run inside
  `reverse-proxy-fpm-1` via `docker exec`.

## Deploy (normal)

```bash
./scripts/deploy.sh
```

This does, with safety checks (aborts on dirty tree, on non-fast-forward pull, or if
the smoke test fails):

1. Preflight: clean working tree, fpm container running.
2. `git fetch` + `git pull --ff-only`.
3. `composer install` (only if `composer.lock` changed — runs inside the fpm container
   as the host uid so file ownership stays correct).
4. `npm ci` (only if `package-lock.json` changed).
5. **`npm run build` — always.** Tailwind utility classes (`h-*`, `w-*`, …) only exist
   in the production CSS once rebuilt. Skipping this is the classic "deployed but
   styles/classes missing" trap, and it's easy to hit after any Blade/template edit.
6. Refresh Laravel caches inside the container: `view:clear`, `cache:clear`,
   `config:cache`.
7. Smoke test: homepage returns HTTP 200 **and** the served HTML references the
   freshly built, content-hashed CSS asset.

Notes:
- `./scripts/deploy.sh --force` forces steps 5–7 even when HEAD is unchanged
  (e.g. the prod tree already contains origin's commits and only needs a rebuild).
- **No container restart needed.** PHP opcache has `validate_timestamps=On` /
  `revalidate_freq=2`, so it picks up changed files within ~2s. If you ever need an
  immediate reset: `docker exec reverse-proxy-fpm-1 sh -c 'kill -USR2 1'` (not normally
  required).
- Vite emits **content-hashed asset filenames**, so browser/CDN caches bust themselves
  on rebuild — nothing to purge. (Exception: non-hashed files like `public/wordmark.png`
  keep the same URL; a user on a browser that caches it may need a hard refresh.)

## Manual equivalents (if the script can't be used)

```bash
git pull --ff-only origin main
npm run build
docker exec reverse-proxy-fpm-1 php /srv/omahub/artisan view:clear
docker exec reverse-proxy-fpm-1 php /srv/omahub/artisan cache:clear
docker exec reverse-proxy-fpm-1 php /srv/omahub/artisan config:cache
```

## Gotchas

- **Host php/composer don't exist** — always qualify artisan/composer with
  `docker exec reverse-proxy-fpm-1 …` (paths relative to `/srv/omahub`).
- **Keep `main` in sync with origin.** If you commit an ops change (like this doc or
  the deploy script) on the prod box, `git push origin main` so the next ff-pull
  doesn't "diverge". `git pull --ff-only` will refuse on a real divergence — don't
  `--force` or create prod merge commits blindly; rebase your local-only commits on
  top of origin instead.
- If you change config/env: refresh with `config:cache` (the script does this). The app
  currently has `APP_DEBUG=false` and a cached `config.php` — editing `.env` alone is
  not enough in production.
