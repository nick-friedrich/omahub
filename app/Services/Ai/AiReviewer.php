<?php

namespace App\Services\Ai;

use App\Enums\AiRecommendation;
use App\Enums\AiReviewStatus;
use App\Enums\PluginStatus;
use App\Enums\RiskLevel;
use App\Models\AiReview;
use App\Models\Plugin;
use App\Models\SecurityFinding;
use App\Models\SecurityScan;
use App\Services\GitHub\GitHubClient;
use App\Services\Security\SecurityScanner;
use App\ValueObjects\GitHubRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Orchestrates an AI review of a plugin at its current commit. It depends on the
 * deterministic scan (running it first if needed), then sends the deterministic
 * findings plus an independent sample of the repository content to the AI client,
 * and persists the resulting advisory review.
 *
 * Reviews are advisory only and never block anything. Like the deterministic scan,
 * a review is idempotent per commit: a completed review for the same SHA is returned
 * without re-calling the model. A failed AI call records a failed review but does
 * not halt the review pipeline.
 */
class AiReviewer
{
    public function __construct(
        private readonly SecurityScanner $scanner,
        private readonly GitHubClient $github,
        private readonly AiClient $client,
        private readonly RepositoryContentSampler $sampler,
        private readonly string $provider,
        private readonly string $model,
        private readonly int $maxReadmeChars,
    ) {}

    public function review(Plugin $plugin): AiReview
    {
        $sha = (string) $plugin->latest_commit_sha;

        if ($sha === '') {
            throw new RuntimeException("Plugin “{$plugin->name}” has no latest commit to review.");
        }

        $lock = Cache::lock('ai-review:'.$plugin->id, 1800);

        if (! $lock->get()) {
            throw new RuntimeException("An AI review for plugin “{$plugin->name}” is already running.");
        }

        try {
            return $this->reviewLocked($plugin, $sha);
        } finally {
            $lock->release();
        }
    }

    private function reviewLocked(Plugin $plugin, string $sha): AiReview
    {
        $existing = AiReview::query()
            ->where('plugin_id', $plugin->id)
            ->where('commit_sha', $sha)
            ->first();

        if ($existing !== null && $existing->status === AiReviewStatus::Succeeded) {
            return $existing;
        }

        $existing?->delete();

        $review = AiReview::query()->create([
            'plugin_id' => $plugin->id,
            'commit_sha' => $sha,
            'status' => AiReviewStatus::Running,
            'provider' => $this->provider,
            'model' => $this->model,
            'started_at' => now(),
        ]);

        try {
            // The AI review relies on the deterministic scan. scan() is idempotent
            // per commit, so this re-uses an existing successful scan when possible.
            $scan = $this->scanner->scan($plugin);

            $repository = new GitHubRepository(
                (string) $plugin->repository_owner,
                (string) $plugin->repository_name,
            );

            $tarball = $this->github->tarball($repository, $sha);
            $sample = $this->sampler->sample($tarball);

            $raw = $this->client->chat($this->buildMessages($plugin, $scan, $sample));

            $result = AiReviewResult::fromJson($raw);

            $review->update([
                'security_scan_id' => $scan->id,
                'status' => AiReviewStatus::Succeeded,
                'risk_level' => $result->riskLevel,
                'recommendation' => $result->recommendation,
                'summary' => $result->summary,
                'concerns' => $result->concerns,
                'raw_response' => $result->rawResponse,
                'finished_at' => now(),
            ]);

            $this->autoUnpublishIfMalicious($plugin, $review);
        } catch (\Throwable $exception) {
            $review->update([
                'status' => AiReviewStatus::Failed,
                'finished_at' => now(),
            ]);

            throw $exception;
        }

        return $review->refresh();
    }

    /**
     * If the AI advisory review of a plugin's latest commit rates it high or
     * critical risk with an "avoid" recommendation, auto-unpublish the plugin
     * so a human moderator can investigate. Restoration is always manual. This
     * is not a publish gate for the deterministic scan — the AI review builds
     * on it, and a clean AI review never blocks or changes anything.
     */
    private function autoUnpublishIfMalicious(Plugin $plugin, AiReview $review): void
    {
        if ($review->status !== AiReviewStatus::Succeeded) {
            return;
        }

        // Only transition a currently-listed plugin; pending/rejected/archived
        // plugins are not visible anyway.
        if ($plugin->status !== PluginStatus::Published) {
            return;
        }

        $risk = $review->risk_level;

        if (! in_array($risk, [RiskLevel::High, RiskLevel::Critical], true)) {
            return;
        }

        if ($review->recommendation !== AiRecommendation::Avoid) {
            return;
        }

        if ($review->commit_sha !== (string) $plugin->latest_commit_sha) {
            return;
        }

        $plugin->markAiUnpublished();
    }

    /**
     * @param  array<int, array{path: string, contents: string}>  $sample
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(Plugin $plugin, SecurityScan $scan, array $sample): array
    {
        $system = <<<'PROMPT'
You are a security reviewer for the Omahub plugin registry, which lists shell
plugins for the Omarchy desktop environment (a Hyprland-based Linux desktop).
You review a plugin repository so a human moderator can decide whether to
publish it. Your review is advisory only.

You are given:
1. The plugin manifest (JSON).
2. The plugin README.
3. The result of an automated deterministic scan: the aggregate risk level and
   every rule finding (rule, severity, file, line, snippet, description).
4. A sample of the repository's source files (path followed by file contents).

Assess the risk a user would ACTUALLY face by installing and running this plugin.
Ignore illustrative examples in documentation (README, docs/*.md) that are not part
of the executable code, such as a documented `curl | sh` one-liner or an ordinary
package-manager install. DO call out real danger: obfuscated code, hidden
persistence or credential theft, destructive commands run during install, or
anything that harms the system without clear user consent.

Return JSON only, with exactly these keys:
{
  "risk_level": "none" | "low" | "medium" | "high" | "critical",
  "summary": "<2-3 sentence plain-language assessment>",
  "concerns": ["<bullet>", ...] or [],
  "recommendation": "install" | "review" | "avoid"
}

Guidance for each value:
- "install": no notable danger found; safe to publish and install.
- "review": borderline; a human should look at the indicated files first.
- "avoid": clearly dangerous or malicious code; do not publish.
- Cross-check the deterministic findings. Your independent risk_level may differ
  from the deterministic scan, and the summary should say why when it does, e.g.
  "the rules flagged the README, but that snippet is documentation only" or "the
  rules missed the obfuscated call in setup.sh".
- Never include anything outside the JSON object.
PROMPT;

        $repository = "{$plugin->repository_owner}/{$plugin->repository_name}";

        /** @var Collection<int, SecurityFinding> $findings */
        $findings = $scan->findings()->get();

        $findingsBlock = $findings->isEmpty()
            ? "None — the deterministic scan found no issues.\n"
            : $findings->map(fn (SecurityFinding $finding) => sprintf(
                '- [%s] %s %s%s: %s%s',
                $finding->severity,
                $finding->rule,
                $finding->file,
                $finding->line !== null ? ':'.$finding->line : '',
                $finding->description,
                $finding->snippet !== null ? ' (snippet: `'.$finding->snippet.'`)' : '',
            ))->implode("\n");

        $sampleBlock = collect($sample)
            ->map(fn (array $entry): string => "### {$entry['path']}\n{$entry['contents']}")
            ->implode("\n\n---\n\n");

        $manifest = json_encode($plugin->manifest_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—';

        $readme = $plugin->readme_markdown === null
            ? '— (no README)'
            : mb_substr($plugin->readme_markdown, 0, $this->maxReadmeChars);

        $riskLevel = (string) ($scan->risk_level ?? 'none');

        $user = <<<PROMPT
Repository: {$repository}
Analyzed commit: {$scan->commit_sha}

Deterministic scan risk level: {$riskLevel}

Deterministic findings:
{$findingsBlock}

Plugin manifest:
{$manifest}

Plugin README:
{$readme}

Sampled repository files:
{$sampleBlock}
PROMPT;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }
}
