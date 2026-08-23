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
- **The security scan needs Docker from inside the app container.** The deterministic
  scan (`php artisan plugins:scan`, or the "Security review" button in the admin) runs
  untrusted repository content inside a disposable Docker sandbox
  (`DockerSandboxRunner`). For that to work, the app container must have the `docker`
  CLI installed and be able to reach the Docker daemon:
  - the simplest option is to mount `/var/run/docker.sock:/var/run/docker.sock` into
    `reverse-proxy-fpm-1` (a Docker-out-of-Docker setup); or
  - run scans from a separate worker that already has Docker access.
  Without this, scan commands fail at container launch. In local/dev, set
  `SCAN_SANDBOX_ENABLED=false` to scan in-process instead (no Docker needed).
  `scripts/deploy.sh` preflights all of this on every deploy and fails with a clear
  message when something is missing.
- **The sandbox image must provide the application's runtime.** `DockerSandboxRunner`
  runs `php artisan scan:execute` inside the container with this repo bind-mounted
  read-only. Reuse the app's own php-fpm image (or one with the same runtime/autoload)
  so the sandbox runs the exact same rule code as the host. There is **no registry
  image** — `deploy.sh` derives the image from the fpm container, or builds
  `/opt/omahub-scan/Dockerfile` if that file exists on the server. Configure via
  `SCAN_SANDBOX_IMAGE` (`config/security_scan.php`).
- **App-in-Docker path mapping.** When the app runs in a container and talks to the host
  daemon, the sandbox `-v` source is resolved by the host daemon, so it must be a host
  path. Set `SCAN_SANDBOX_HOST_REPO_PATH` to the host path of this repo; leave it unset
  when PHP runs directly on the host (local dev).
- **Keeping scans current.** The public plugin page shows the latest scan as a review
  panel (risk level, analyzed commit, findings) and marks it stale when the analyzed
  commit predates the plugin's latest indexed commit. Nothing fetches GitHub live per
  page view. Freshness comes from the scheduler (see `routes/console.php`): an hourly
  `plugins:refresh` updates commits at :10, then `plugins:scan --stale` at :40
  re-scans only plugins whose latest commit has no successful scan (unchanged
  plugins are skipped cheaply via GitHub ETag / If-None-Match). **These run only
  if a cron triggers Laravel's scheduler** — add one host cron line:
  ```bash
  * * * * * docker exec reverse-proxy-fpm-1 php /srv/omahub/artisan schedule:run >> /dev/null 2>&1
  ```
- **Keep `main` in sync with origin.** If you commit an ops change (like this doc or
  the deploy script) on the prod box, `git push origin main` so the next ff-pull
  doesn't "diverge". `git pull --ff-only` will refuse on a real divergence — don't
  `--force` or create prod merge commits blindly; rebase your local-only commits on
  top of origin instead.
- If you change config/env: refresh with `config:cache` (the script does this). The app
  currently has `APP_DEBUG=false` and a cached `config.php` — editing `.env` alone is
  not enough in production.
