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
  - **Socket permission gotcha.** The host socket is `root:docker` (GID 988, mode 660).
    PHP-FPM workers run as user `app` (uid 1000), which must be a member of a group with
    GID 988 (`docker`) inside the container — otherwise scans fail with
    "permission denied while trying to connect to the docker API". `deploy.sh` only
    preflights the socket as root, so it does **not** catch this. Verify as `app`:
    `docker exec reverse-proxy-fpm-1 su app -s /bin/sh -c 'docker version'`. If missing,
    add GID 988 + membership in `/etc/group` (this container has no `usermod`/`gpasswd`):
    `printf 'docker:x:988:app\n' >> /etc/group`, then `kill -USR2 1`. These container
    edits are **ephemeral** — re-apply after the container is recreated (see below).
  - **Scan memory.** The scan downloads full repo tarballs and reads them into memory,
    and the throwaway sandbox runs with PHP's default 128M CLI limit, so large repos
    OOM (the host fpm shows "Allowed memory size exhausted", the sandbox exits 255 /
    "Broken pipe"). The sandbox limit is already raised in code
    (`docker run … php -d memory_limit=1G artisan scan:execute`). The **host fpm** limit
    is set via `/usr/local/etc/php/conf.d/zz-memory.ini` (`memory_limit = 1G`) — an
    **ephemeral** container edit; re-apply (or bake into the image) if the container is
    recreated.
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
  commit predates the plugin's latest indexed commit. Visiting a plugin that has not
  been indexed for 10 minutes dispatches one ETag-aware refresh on Laravel's deferred
  queue after the response; a cache gate deduplicates visits, and the page polls and
  reloads when indexing finishes. The scheduler remains the fallback (see
  `routes/console.php`): an hourly `plugins:refresh` updates commits at :10, then
  `plugins:scan --stale` at :40 re-scans only plugins whose latest commit has no
  successful scan (unchanged plugins are skipped cheaply via GitHub ETag /
  If-None-Match). **Scheduled commands only run if a cron triggers Laravel's
  scheduler** — add one host cron line:
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
- **Scans are serialized per plugin.** Starting a scan for a plugin that already has an
  in-flight scan throws "A scan for plugin … is already running" (`SecurityScanner` holds
  a per-plugin cache lock). Running `plugins:scan` while the hourly scheduled scan (`:40`,
  `withoutOverlapping`) is in flight will report those plugins as FAILED — they are picked
  up on the next run, so this is safe but noisy. The old behaviour (both processes racing
  on the same `SecurityScan` row) caused FK violations / "No query results for model".

## Running tests (host has no PHP)

- Run tests via the app's own php-fpm image, bind-mounting the repo at the in-container
  path. The **security-scan tests need the Docker daemon**, so mount the socket exactly
  like the prod fpm container does:
  ```bash
  docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
    -v "$PWD":/srv/omahub -w /srv/omahub php-fpm:latest vendor/bin/phpunit
  ```
  (`php-fpm:latest` is the image `reverse-proxy-fpm-1` runs; omit the `-v` socket mount
  only when you want to skip the scan tests — they will error without it.)
- **A stale compiled config breaks tests.** `bootstrap/cache/config.php` is a generated,
  git-ignored artifact created by `php artisan config:cache` (deploy step 6). It hard-codes
  the absolute prod DB path (`/srv/omahub/database/database.sqlite`), and when it exists
  Laravel ignores `.env`/phpunit overrides — so feature tests fail with
  `Database file at path [.../database.sqlite] does not exist`. Fix: delete it with
  `rm bootstrap/cache/config.php` (== `php artisan config:clear`). This only removes the
  config cache — **it never touches the database**. The deploy script regenerates it with
  `config:cache` on the server, which is correct there.

## README rendering (relative links/images)

- READMEs are stored as **raw markdown** (`plugins.readme_markdown`) and rendered
  **per-request** in `PluginController@show` / `@index` (and `HomeController`) via
  `App\Services\Markdown\MarkdownRenderer`. There is no cached HTML — code changes to
  the renderer take effect immediately on deploy; no re-index is needed.
- `MarkdownRenderer` rewrites **relative image AND link** URLs against the repo's raw
  GitHub base (`Plugin::rawContentBaseUrl()`), so `[LICENSE](LICENSE)` → a raw URL rather
  than a site path that would 404. Page anchors (`#…`) and absolute/external URLs are
  left untouched. If you touch link handling, keep the tests in
  `tests/Unit/Services/MarkdownRendererTest.php` green — they cover relative rewrite,
  anchor preservation, and the no-base-url case.
