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

# V0.3 — Deterministic security analysis

Security analysis must be tied to an exact commit. Add the models only now:

```text
SecurityScan
SecurityFinding
```

Each scan records:

```text
plugin_id
commit_sha
status
risk_level
started_at
finished_at
```

Initial deterministic checks can identify behavior such as:

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

Each finding records severity, rule, file, line, and description. Results must be reproducible for the same commit.

Display cautious language:

```text
No obvious issues detected
Review recommended
Potentially dangerous behavior detected
```

Always display:

```text
Automated analysis only — not a security guarantee.
```

Show the analyzed commit and date. Rescan only when the upstream commit changes. This is the point at which database-backed Laravel queues and the scheduler are likely justified; SQLite remains acceptable until measured concurrency or deployment constraints require a change.

AI analysis is optional and comes only after deterministic scanning produces useful results. It must return structured output and must never replace or hide deterministic findings.

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
