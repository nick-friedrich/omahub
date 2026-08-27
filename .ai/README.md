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
| `Models/` | `Plugin` (core entity; see `rawContentBaseUrl()`, `githubBlobUrl()`), `PluginSubmission`, `SecurityScan`, `SecurityFinding`, `Category`, `Tag`, `User`. |
| `Enums/` | `PluginStatus`, `SubmissionStatus`, `SecurityScanStatus`, `RiskLevel`. |
| `Services/GitHub/` | `GitHubClient` — GitHub API interaction (ETag-aware). |
| `Services/Markdown/` | `MarkdownRenderer` — README → HTML; rewrites relative image/link URLs to raw GitHub. |
| `Services/Plugins/` | `PluginDirectory` (browse/find), `GitHubRepositoryImporter` (index a repo), `ManifestValidator`, `PluginSubmissionService`, `PluginVisitRefresher` (stale-refresh gate). |
| `Services/Security/` | `SecurityScanner` orchestrator for the plugin security scan. |
| `Security/` | The deterministic scan engine: `ScanEngine`, `SandboxRunner` (`DockerSandboxRunner` / `LocalSandboxRunner`), `Rule`/`SecurityRule` + `Rules/*`, `DocumentationFile`. |
| `Console/Commands/` | Artisan commands — `plugins:refresh`, `plugins:scan`, `scan:execute`, `plugin:import`, submission approve/reject/list, `user:make-admin`, `security-scan:recalculate-risk`. |
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
panel. See `config/security_scan.php` for sandbox/mode config and
`app/Security/README` (add this next) for the engine details.
