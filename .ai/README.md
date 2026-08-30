# .ai — project map

Notes for AI agents (and humans) on how this codebase is organized. This is a living
map — add a file here when you add a subsystem, and keep entries pointed at the current
source.

## What this app is

Omahub is a community registry for **Omarchy** shell plugins. It mirrors plugin repos
submitted by users, indexes their metadata/manifest/READMEs from GitHub, renders plugin
pages, and runs a deterministic security scan of untrusted plugin code inside a Docker
sandbox. Built on Laravel 12 (PHP) + Blade + Tailwind/Vite. There is **no queue worker,
scheduler, or cron** in production — see `agents.md`.

## Top-level layout

| Path | What lives there |
| --- | --- |
| `app/` | PHP application code (below). |
| `routes/` | `web.php` (HTTP routes) and `console.php` (scheduled/artisan commands). |
| `config/` | Laravel config + app-specific `ai.php`, `security_scan.php`. |
| `database/` | Migrations, model factories, seeders, and the prod sqlite file. |
| `resources/views/` | Blade views (mirrors the `app/` + public pages). |
| `tests/` | Feature + unit tests (mirror `app/` namespaces). |
| `scripts/deploy.sh` | The production deploy pipeline (Caddy + shared php-fpm bind-mount). |
| `public/` | Web root; built Vite assets in `public/build/`. |
| `assets/` | Source assets built by Vite. |
| `agents.md` | Deployment & operating notes — read before touching prod or running tests. |

## `app/` map

| Path | Purpose |
| --- | --- |
| `Http/Controllers/` | `HomeController`, `PluginController`, `CategoryController`, `SearchController`, `ResourcesController`, `SubmitController`, plus `Auth/` (GitHub login) and `Admin/` (moderation UI). |
| `Http/Requests/` | Form validation: `SubmitPluginRequest`, `Admin/*`. |
| `Http/Middleware/` | `EnsureUserIsAdmin`. |
| `Models/` | `Plugin` (core entity; see `rawContentBaseUrl()`, `githubBlobUrl()`), `PluginSubmission`, `SecurityScan`, `SecurityFinding`, `AiReview`, `Category`, `Tag`, `User`. |
| `Enums/` | `PluginStatus`, `SubmissionStatus`, `SecurityScanStatus`, `AiReviewStatus`, `AiRecommendation`, `RiskLevel`. |
| `Services/GitHub/` | `GitHubClient` — GitHub API interaction (ETag-aware). |
| `Services/Markdown/` | `MarkdownRenderer` — README → HTML; rewrites relative image/link URLs to raw GitHub. |
| `Services/Plugins/` | `PluginDirectory` (browse/find), `GitHubRepositoryImporter` (index a repo), `ManifestValidator`, `PluginSubmissionService`, `PluginVisitRefresher` (stale-refresh gate). |
| `Services/Security/` | `SecurityScanner` orchestrator for the plugin security scan. |
| `Services/Ai/` | `AiReviewer` (orchestrates the advisory AI review on top of the deterministic scan), `AiClient` interface + `OpenRouterClient` (DeepSeek via OpenRouter), `AiReviewResult` (parsed/validated output), `RepositoryContentSampler` (bounded repo content sample for the prompt). |
| `Security/` | The deterministic scan engine: `ScanEngine`, `SandboxRunner` (`DockerSandboxRunner` / `LocalSandboxRunner`), `Rule`/`SecurityRule` + `Rules/*`, `DocumentationFile`. |
| `Console/Commands/` | Artisan commands — `plugins:refresh`, `plugins:scan`, `plugins:ai-review`, `scan:execute`, `plugin:import`, submission approve/reject/list, `user:make-admin`, `security-scan:recalculate-risk`. |
| `Jobs/` | `RefreshPlugin` (queued, deferred ETag-aware refresh). |
| `ValueObjects/` | `GitHubRepository`. |
| `Rules/` | `GitHubRepositoryUrl` validation rule. |
| `Support/` | `Format` helpers. |
| `Exceptions/` | Domain exceptions (validation, GitHub, manifest, duplicates). |
| `Providers/` | `AppServiceProvider` (registers `MarkdownRenderer` as a singleton). |

## Testing

- `tests/Unit` and `tests/Feature` mirror `app/` structure.
- Feature tests use an **in-memory sqlite** override from `phpunit.xml`. If a stale
  `bootstrap/cache/config.php` exists they fail with a "database does not exist" error —
  delete that file to fix (see `agents.md`).
- Security-scan tests require the Docker daemon: run phpunit with
  `/var/run/docker.sock` mounted (see `agents.md`).

## Security scan subsystem (in short)

Submitted/repository plugin code is run in an isolated Docker sandbox. The scan engine
checks configured sources against security rules (credential access, eval, destructive
filesystem, obfuscation, external hosts, etc.), producing `SecurityScan` +
`SecurityFinding` records surfaced on the public plugin page as a "Security review"
panel. See `config/security_scan.php` for sandbox/mode config.

- Flow: `GitHubClient::tarball()` downloads a repo tar.gz at an exact commit →
  `SecurityScanner::scan()` (per-plugin cache lock, so the scheduler and ad-hoc runs
  never race) → `SandboxRunner` (`DockerSandboxRunner` pipes the tarball over stdin to a
  disposable `docker run` of the app image running `scan:execute`) → `ScanEngine` +
  `Security/Rules/*` → persisted scan + findings.
- The sandbox runs `php -d memory_limit=1G artisan scan:execute` because full tarballs
  exceed the default 128M CLI limit. The host fpm also needs a raised limit
  (`/usr/local/etc/php/conf.d/zz-memory.ini`) and GID-988 docker-group membership for the
  `app` user — see `agents.md`. These are ephemeral container edits.
- Scheduler (routes/console.php): `plugins:refresh` hourly at :10, `plugins:scan --stale`
  hourly at :40, both `withoutOverlapping`.

## AI review subsystem (advisory, manual)

On top of the deterministic scan there is an advisory AI review (`AiReview`), intended
as context for the human reviewer. It is **not** a publish gate and never hides
deterministic findings.

- Flow: `AiReviewer::review($plugin)` ensures a deterministic scan exists for the
  current commit, downloads the tarball again, samples a bounded set of text files
  (`RepositoryContentSampler`), and sends the deterministic findings + manifest + README
  + file sample to `OpenRouterClient` (DeepSeek flash v4 latest via OpenRouter,
  `config/ai.php`) asking for strict JSON. The parsed `AiReviewResult` is persisted
  keyed to `(plugin_id, commit_sha)` — idempotent per commit like the scan.
- A failed AI call records a failed review and throws (so the caller can surface it);
  it never blocks the pipeline.
- Triggers: `php artisan plugins:ai-review` (`--ids/--after/--stale/
  --dry-run/--limit`), the "AI review" button on the admin plugin edit page, the
  "Run scan" / "Run AI review" buttons on the admin submission page (which run the
  deterministic scan and AI review directly on the submission's imported plugin),
  and — like the deterministic scan — the hourly scheduler
  `plugins:ai-review --stale` at :50 (`routes/console.php`), which only re-reviews
  plugins whose latest commit has no successful review yet (bounded cost).
- Public plugin pages render the latest AI review via
  `components/plugin-ai-notice.blade.php` (sits below the deterministic
  `plugin-security-notice`), including a "How this check works" explainer.
- Requires `AI_API_KEY` (OpenRouter). Without it, AI reviews fail with a clear message.
