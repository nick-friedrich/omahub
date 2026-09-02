<?php

namespace App\Services\Ai;

use App\Enums\AiRecommendation;
use App\Enums\RiskLevel;
use App\Exceptions\AiRequestException;

/**
 * The parsed, validated outcome of an AI review call: the structured risk
 * assessment plus the raw assistant response for auditing.
 */
final class AiReviewResult
{
    /**
     * @param  list<string>  $concerns
     */
    public function __construct(
        public readonly RiskLevel $riskLevel,
        public readonly AiRecommendation $recommendation,
        public readonly string $summary,
        public readonly array $concerns,
        public readonly string $rawResponse,
    ) {}

    /**
     * Parse a raw assistant response, validating the expected JSON shape. Throws
     * an AiRequestException when the model returns something unusable so a bad
     * reply is recorded as a failed review rather than persisted as a success.
     *
     * The model occasionally strays from the exact requested schema — camelCase
     * keys, `risk` instead of `risk_level`, or `explanation` instead of
     * `summary`. Accepted aliases are normalized, and a missing recommendation
     * is derived conservatively from the risk level (never `avoid`, so an
     * inferred recommendation can never trigger auto-unpublish). Values that
     * are genuinely bogus still fail the review.
     */
    public static function fromJson(string $rawResponse): self
    {
        $data = json_decode($rawResponse, true);

        if (! is_array($data)) {
            throw new AiRequestException('The AI review did not return valid JSON.');
        }

        $riskLevel = self::enumValue($data, [RiskLevel::class, 'risk_level', 'riskLevel', 'risk']);
        if ($riskLevel === null) {
            throw new AiRequestException('The AI review returned an invalid risk_level.');
        }

        $recommendation = self::enumValue($data, [AiRecommendation::class, 'recommendation', 'recommendationLabel']);

        $summary = self::firstString($data, ['summary', 'explanation', 'risk_summary', 'riskSummary']);
        if ($summary === '') {
            throw new AiRequestException('The AI review returned an empty summary.');
        }

        return new self(
            riskLevel: $riskLevel,
            recommendation: $recommendation ?? self::deriveRecommendation($riskLevel),
            summary: $summary,
            concerns: self::concerns($data),
            rawResponse: $rawResponse,
        );
    }

    /**
     * Resolve an enum value from the first present key alias. Values are
     * trimmed and lowercased before matching; non-conforming values yield null
     * and a genuine bogus value fails the review.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @param  array<int, string>  $keys  first entry is the enum class
     * @return T|null
     */
    private static function enumValue(array $data, array $keys): ?\BackedEnum
    {
        $enumClass = array_shift($keys);
        $value = self::firstString($data, $keys);

        return $value === '' ? null : $enumClass::tryFrom(strtolower($value));
    }

    /** @param array<int, string> $keys */
    private static function firstString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private static function deriveRecommendation(RiskLevel $riskLevel): AiRecommendation
    {
        // Never infer `avoid` — auto-unpublish requires the model to say it.
        return in_array($riskLevel, [RiskLevel::High, RiskLevel::Critical], true)
            ? AiRecommendation::Review
            : AiRecommendation::Install;
    }

    /** @return list<string> */
    private static function concerns(array $data): array
    {
        $raw = $data['concerns'] ?? [];

        return collect((array) $raw)
            ->map(fn (mixed $concern): string => trim((string) $concern))
            ->filter(fn (string $concern): bool => $concern !== '')
            ->values()
            ->all();
    }
}