# Coding Agent Plan — Omarchy Registry + CLI

Build an independent Omarchy plugin registry with Laravel. Start with the smallest useful registry: import a public GitHub repository, store accurate metadata, and render a searchable directory.

The long-term product combines discovery, community feedback, security analysis, and a package-manager-style CLI. Do not build those pieces before the basic registry is useful.

## Guiding principles

- Start with a conventional Laravel monolith.
- Use server-rendered Blade pages.
- Use Alpine.js only for small client-side interactions.
- Add HTMX only when it clearly improves a specific interaction; do not introduce Livewire, Inertia, or a SPA framework initially.
- Use SQLite for local development and the initial deployment.
- Keep application code database-portable, but do not add PostgreSQL infrastructure preemptively.
- Controllers should call shared application services. The Blade website does not need to call the public API.
- GitHub is the source of truth for plugin contents and repository metadata.
- Store repository metadata, not plugin archives or binaries.
- Prefer synchronous work initially. Introduce queued jobs only when an operation is too slow or must be retried outside the request.
- No Redis, microservices, Elasticsearch, or AI in the initial release.

---

# V0.1 — Useful registry

V0.1 should answer one question well: **what Omarchy plugins exist, and what does each repository contain?**

## Phase 1 — Project foundation

Create a Laravel application using:

- Laravel
- SQLite
- Blade
- Tailwind using Laravel's default frontend setup
- Alpine.js for small interactions, if needed
- PHPUnit or Pest, following the generated application's default

Initial entities:

```text
Plugin
Category
Tag
PluginSubmission
```

Do not create users, reviews, versions, scans, findings, favorites, moderation logs, or a separate `Repository` model yet. Add them when their feature is implemented.

A plugin should initially track:

```text
id
slug
name
description
repository_url
repository_owner
repository_name
author_name
author_url
license
homepage_url
icon_url
manifest_data (JSON)
default_branch
latest_commit_sha
latest_version
stars_count
forks_count
open_issues_count
is_archived
last_pushed_at
last_indexed_at
published_at
status
created_at
updated_at
```

Use only the statuses needed by V0.1:

```text
pending
published
rejected
archived
```

Categories and tags have a many-to-many relationship with plugins. Avoid adding fields without a current page or importer requirement.

### Foundation acceptance criteria

- A fresh clone can be started using documented commands.
- `php artisan migrate` creates a working SQLite database.
- Factories and a small development seeder provide realistic plugin records.
- Core model and status behavior has basic test coverage.

---

## Phase 2 — GitHub repository importer

Build the first genuinely useful feature: importing an Omarchy plugin from a public GitHub repository.

Create one application service, for example:

```text
GitHubRepositoryImporter
```

Input:

```text
https://github.com/owner/repository
```

For V0.1, support public GitHub repositories only. Use GitHub's API with an optional token configured through the environment. Keep HTTP access behind a small GitHub client so tests can use fake responses.

Import flow:

```text
Normalize and validate the GitHub URL
↓
Fetch repository metadata
↓
Locate manifest.json on the default branch
↓
Validate the fields the registry currently understands
↓
Fetch README and license metadata
↓
Read the current commit SHA
↓
Create or update the plugin
```

Collect:

```text
repository owner and name
default branch
repository description and homepage
stars
forks
open issues
archived state
last push date
license
latest commit SHA
manifest contents
README contents
latest release or tag, when available
```

Store rendered or sanitized README content only if needed for display; otherwise store source Markdown and render it safely. Never render untrusted HTML without sanitization.

Repository identity should be based on normalized GitHub owner/name, with a database uniqueness constraint. Importing the same repository twice must update the existing plugin rather than create a duplicate.

Failures should produce clear errors for:

- invalid or unsupported URLs
- missing repositories
- GitHub rate limits
- missing manifests
- malformed manifests
- network failures

Add a command:

```bash
php artisan plugins:import https://github.com/foo/bar
```

The command may run synchronously in V0.1.

### Importer acceptance criteria

- Importing a valid repository creates one plugin.
- Re-importing it updates that plugin.
- Invalid and missing manifests do not leave partial records.
- HTTP calls are tested with Laravel HTTP fakes.
- At least one fixture represents a real-looking Omarchy plugin manifest.

---

## Phase 3 — Public Blade website

Build a server-rendered public website.

Pages:

```text
/
/plugins
/plugins/{slug}
/categories/{slug}
/search?q=
/submit
```

The homepage should remain small:

- recently updated plugins
- newest plugins
- popular plugins, using GitHub stars for now

The plugin index should support basic pagination and query-string filtering by category or tag. Search can initially use portable SQL against name and description. SQLite FTS is unnecessary until normal queries are demonstrably inadequate.

The plugin detail page should display available data without inventing missing information:

- name and description
- author
- repository link
- license
- latest release/tag, if present
- last repository update
- GitHub activity
- categories and tags
- README
- installation instructions from the manifest or README, if available

Use Blade partials/components for repeated UI. Use ordinary links and forms first. Add Alpine or HTMX only for a concrete usability improvement such as filter updates; the pages must remain usable without JavaScript.

Do not spend significant time on visual polish. Accuracy, readable pages, and useful empty/error states matter more.

### Website acceptance criteria

- Published plugins can be browsed, searched, and filtered.
- Pending and rejected plugins are not publicly visible.
- README output is sanitized.
- Pagination and empty states work.
- Main public pages have feature tests.

---

## Phase 4 — Minimal submission flow

Allow anyone to submit a public GitHub repository URL.

Flow:

```text
Submit repository URL
↓
Validate and normalize URL
↓
Reject duplicate pending submissions
↓
Run the importer
↓
Create a submission record
↓
Publish automatically if validation succeeds, or leave pending for review
```

Start with a normal Blade form. Add rate limiting and a honeypot. CAPTCHA is unnecessary unless abuse appears.

A submission should contain only operational data:

```text
id
repository_url
plugin_id (nullable)
status
failure_reason (nullable)
submitted_at
reviewed_at (nullable)
created_at
updated_at
```

Decide on one explicit initial policy:

- **Automatic publication:** valid manifests are immediately published; or
- **Manual publication:** valid imports remain pending until an admin command approves them.

Prefer manual publication for the first public deployment. A full admin UI is not required: provide simple Artisan commands to list, approve, and reject pending submissions.

### Submission acceptance criteria

- A visitor can submit a valid public GitHub URL.
- Duplicate and malformed submissions receive useful feedback.
- Import errors are recorded without exposing secrets or stack traces.
- A maintainer can approve or reject a submission from the CLI.

---

## Phase 5 — Small, stable public API

Add the API after the importer and public data model have settled. The website should continue rendering Blade directly from shared services/query objects rather than making HTTP requests to itself.

Initial endpoints:

```http
GET /api/v1/plugins
GET /api/v1/plugins/{slug}
GET /api/v1/categories
GET /api/v1/search?q=
```

Support only filters and sorting already proven useful by the website:

```text
category
tag
updated since
newest
updated
stars
```

Use Laravel API resources, pagination, request validation, and response tests. Version the routes from the beginning. Do not add speculative version or security endpoints before those models exist.

### V0.1 release criteria

Ship V0.1 when all of the following work:

```text
SQLite-backed Laravel application
GitHub importer and manifest validation
Idempotent re-imports
Public plugin pages
Categories and tags
Basic search and filtering
Repository submission
Maintainer approval commands
Small versioned REST API
Setup and deployment documentation
```

---

# V0.2 — Accounts and community

Build this only after V0.1 is deployed and the plugin representation has proven adequate.

## Authentication

Add GitHub OAuth when accounts are needed. Users can:

- sign in
- view a basic profile
- submit plugins while authenticated
- rate and review plugins
- edit or delete their own reviews
- favorite plugins

Store only required GitHub identity data.

## Author claiming

Allow a maintainer to claim a plugin by verifying suitable access to the upstream GitHub repository.

Keep trust concepts separate:

```text
author_verified
manifest_valid
automated_security_scan
manual_review
```

Never collapse these into a generic `verified` boolean. “Author verified” must not imply that a plugin is secure.

## Ratings and reviews

Rules:

- one 1–5 rating per user per plugin
- rating can be changed
- review text is optional
- users can delete their own reviews
- reviews can be reported

Show the plain average, count, distribution, and recent reviews. Do not create a complex ranking algorithm.

## Basic moderation

Add only the tools needed to review submissions, reported reviews, and blocked or archived plugins. Record moderator actions in an audit log once web-based moderation exists.

---

# V0.3 — Plugin review pipeline

A three-stage review pipeline checks every imported plugin before (and after) it is listed.

```text
 Deterministic scan ─► AI review ─► Human review
   (sandboxed rules)   (OpenRouter)   (decision)
```

Decisions locked in:

- **Human approval is the only gate.** Deterministic and AI results are advisory context
  shown to the reviewer; they never block publication on their own.
- **Deterministic first.** Build and prove the deterministic scan before any AI work.
- **AI provider:** configurable model via **OpenRouter** (default DeepSeek, config-driven
  `AI_MODEL`). AI must return structured output and must never replace or hide
  deterministic findings.
- **Fetch real content by tarball.** Download `https://codeload.github.com/{owner}/{repo}/tar.gz/{sha}`
  for the exact commit — one request, full file coverage, reproducible — instead of
  many per-file `contents` calls.
- **Sandbox the scan in Docker.** Untrusted repository content is downloaded and
  extracted inside a disposable container, never on the host.
- **Run synchronously via Artisan commands** until a measured need for queues.

Trust stays explicit and separate (`author_verified`, `manifest_valid`,
`automated_security_scan`, `manual_review`) — a scan result never implies safety.

## Data model

```text
SecurityScan
SecurityFinding
AiReview
```

`SecurityScan` records:

```text
plugin_id
commit_sha
status           running | succeeded | failed
risk_level       none | low | medium | high | critical
rules_run        JSON
started_at
finished_at
```

Unique on `(plugin_id, commit_sha)`: rescanning the same commit is a no-op; the scan is
re-run only when the upstream commit changes (idempotency per commit).

`SecurityFinding` records severity, rule, file, line, snippet, and description — matching
the rule that produced it.

`AiReview` records provider, model, status, summary, risk_level, recommendation, the
parsed structured `output`, and the `raw_response`, keyed to the same `(plugin_id, commit_sha)`.

## Stage 1 — Deterministic scan (in progress)

- `GitHubClient::tarball()` downloads the repo tarball for a commit.
- A `SandboxRunner` (behind an interface, faked in tests) shells to
  `docker run --rm` with a scanner image that downloads + extracts the tarball **inside
  the container**, walks the files, and prints a findings JSON to stdout. The scanner image
  mounts this repo read-only and runs the same shared rule classes, keeping the sandbox
  and application code in sync.
- A `SecurityScanner` aggregates rule findings into a whole-scan `risk_level`.
- Rules live in `app/Security/Rules/*.php` (id, severity, `matchesFile`, `scan`) and cover:

  - `sudo`
  - destructive filesystem commands
  - `curl | sh` or `wget | sh`
  - permission and ownership changes
  - systemd or cron persistence
  - shell profile modification
  - credential or SSH key access
  - package manager operations
  - downloads and external hosts
  - obfuscated commands
  - decode-and-execute patterns
  - `eval`
  - writes outside expected plugin directories

- **Documentation findings are reported but capped.** Files like `README.md`, `docs/*`,
  `CHANGELOG`, `LICENSE` describe usage rather than execute code — a `curl | sh`
  install snippet in a README is illustrative, not a vulnerability. Such findings are
  still surfaced (tagged `docs` in the UI, with a link to the file at the scanned
  commit) but never drive the whole-scan risk level: risk reflects executable code,
  and a scan whose only findings are documentation is capped at `Low`. Running
  `php artisan security:recalibrate` recomputes risk levels after a policy/severity
  change without re-scanning.

- Command (synchronous, resumable in batches, matching the `plugins:refresh` style):

  ```bash
  php artisan plugins:scan --ids=1,2,3 | --after=<id> | --stale
  php artisan plugins:scan --stale --limit=50
  php artisan plugins:scan --dry-run
  ```

  (`--stale` targets only plugins whose latest commit has no successful scan — the
  cheap way to keep reviewed state current after a refresh.)

- Config: `SCAN_SANDBOX_IMAGE` (a **local** image tag built on the server — never a
  registry image, see Production deployment concern), `SCAN_SANDBOX_HOST_REPO_PATH`
  (host path of this repo when the app runs in Docker, see Production deployment
  concern), `SCAN_SANDBOX_ENABLED` (when disabled, scan runs directly for local dev /
  tests), codeload URL.

## Stage 2 — AI review (implemented)

- `AiClient` interface + `OpenRouterClient` implementation (DeepSeek flash v4 latest via
  OpenRouter). Config in `config/ai.php`: `AI_API_KEY`, `AI_MODEL`, `AI_BASE_URL`.
- `AiReviewer` sends the deterministic findings plus an independent sample of the repo
  content (manifest, README, sampled source files) with a strict prompt requesting
  structured JSON. The parsed, validated shape is persisted as an `AiReview`
  (`AiReviewStatus`, `AiRecommendation`, risk_level, summary, concerns, raw_response)
  keyed to `(plugin_id, commit_sha)`.
- A failed AI call records a failed review and does not halt the pipeline (advisory).
- Triggered manually: `plugins:ai-review` command, admin plugin page, and the
  "Run scan" / "Run AI review" buttons directly on submissions.

## Stage 3 — Human review (deferred)

- Extend the admin submission and plugin pages to show a review panel: scan risk level,
  findings (rule/severity/file/line/snippet), AI summary/concerns, the analyzed commit and
  date, plus cautious language.
- The existing approve/reject buttons remain the only publish gate.

## Display language (all stages)

```text
No obvious issues detected
Review recommended
Potentially dangerous behavior detected
```

Always display:

```text
Automated analysis only — not a security guarantee.
```

Show the analyzed commit and date.

The public plugin detail page renders the latest scan as a full review panel (verdict,
risk level, analyzed commit, scan date, findings) and warns when the analyzed commit
predates the plugin's latest indexed commit ("Newer commit … not yet reviewed"). No
live GitHub fetch happens per page view; freshness comes from a daily schedule
(`routes/console.php`): `plugins:refresh` at 03:10 updates commits, then
`plugins:scan --stale` at 04:10 re-scans only plugins whose latest commit has no
successful scan. That schedule needs a host cron running `php artisan schedule:run`
(see `agents.md`).

## Production deployment concern

The app runs in `reverse-proxy-fpm-1` (repo bind-mounted). Launching sandbox containers
requires Docker access from the app — either mount `/var/run/docker.sock` into that
container or run the scanner as a separate worker container. Decide this before
deploying scans to production.

**Sandbox image: built on the server, never pushed to a registry.** There is no
`ghcr.io`/Docker Hub image for the scanner — that was tried and the tag does not exist
(`manifest unknown`), and we don't want to publish one. `scripts/deploy.sh` preflights
the sandbox on every deploy (docker CLI in the app container, host daemon reachable,
image present) and builds or picks the image itself:

1. **Reuse the app's own runtime image already on the server** (default). The sandbox
   only needs PHP plus the extensions the scan uses (`PharData`, `zlib`, …); the repo is
   bind-mounted read-only, so the application code comes from the mount and stays in
   sync with the host automatically. `deploy.sh` derives it from the fpm container's own
   image — no Dockerfile at all.
2. **Only if the scan needs a different runtime:** keep a dedicated `Dockerfile` **on the
   server only, outside the repo** (default path `/opt/omahub-scan/Dockerfile` — never
   committed, never pushed). `deploy.sh` builds it there (`docker build -t omahub-scan .`)
   when the file exists and points the app at the local tag. Rebuild on the server
   whenever the runtime changes.

**App-in-Docker path mapping (Docker-out-of-Docker).** The app runs inside
`reverse-proxy-fpm-1` and launches sandbox containers through a mounted
`/var/run/docker.sock`. The sandbox `-v` source is resolved by the **host** daemon, so
when the app is containerised, set `SCAN_SANDBOX_HOST_REPO_PATH` to the host path of
this repo (e.g. `/opt/omahub`). When PHP runs on the host (local dev), leave it unset —
`base_path()` is already a host path.

**Migrations run on deploy.** `scripts/deploy.sh` runs `artisan migrate --force` on every
deploy, so new migrations (e.g. `security_scans` / `security_findings`) reach production
without manual steps.

## Testing

- Unit tests per rule, with fixtures under `tests/Fixtures/security/` (a clean repo and a
  repo containing rule-triggering patterns).
- `SecurityScanner` and `SandboxRunner` tests with a fake runner; idempotency test (same
  commit → one `SecurityScan`; new commit → rescans).
- Feature test for `plugins:scan` using an extended `FakesGitHub` (codeload route) and the
  fake sandbox.

**Later, only when justified:** real-time auto-rescan the moment an upstream commit
changes (needs scheduler/queues; the daily stale-scan covers this in batch), community
ratings brought in from V0.2, and making scans block
publication if policy later changes.

---

# V0.4 — Versions and CLI

## Version model

Add `PluginVersion` only when installation work begins. A version maps to:

```text
plugin
Git tag or release
exact commit SHA
release date
supported Omarchy version, when declared
security scan
```

The exact relationship is mandatory:

```text
scanned commit = installed commit
```

## Separate CLI project

Create a separate CLI project that consumes the versioned API.

First read-only commands:

```bash
oma search <query>
oma info <plugin>
oma security <plugin>
oma list
```

Then add installation:

```bash
oma install <plugin>
oma inspect <plugin>
oma uninstall <plugin>
oma update <plugin>
oma update --all
oma outdated
```

Before installation, show the exact version and commit, files or locations affected, commands executed, external hosts contacted, elevated privileges, and security findings. Require confirmation unless explicitly running non-interactively.

Do not install an unscanned moving branch or whatever happens to be the latest upstream commit.

---

# Later, only when justified

Potential later work:

- automatic GitHub discovery
- compatibility resolution
- privacy-conscious, opt-in installation statistics
- trending based on real registry usage
- AI-assisted security review
- richer moderation workflows
- PostgreSQL, if SQLite becomes an observed bottleneck
- Redis, if queue or cache load requires it
- dedicated search infrastructure, if database search becomes inadequate

Do not collect usernames, machine IDs, filesystem information, or IP-derived identifiers for CLI analytics.

Only after the plugin workflow is proven should the registry consider expanding to themes, widgets, scripts, wallpapers, applications, or general Omarchy configuration.

## Product definition

> **An independent Omarchy registry with discovery, community ratings, automated security analysis, and a package-manager-style CLI.**

The immediate implementation scope is narrower:

> **Import public Omarchy plugin repositories accurately and present them in a simple, searchable Blade application.**

Start with Phase 1, then Phase 2, then Phase 3. Do not begin authentication, security analysis, AI, or the CLI until the registry and importer are solid.
