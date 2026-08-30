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
     */
    public static function fromJson(string $rawResponse): self
    {
        $data = json_decode($rawResponse, true);

        if (! is_array($data)) {
            throw new AiRequestException('The AI review did not return valid JSON.');
        }

        $riskLevel = RiskLevel::tryFrom((string) ($data['risk_level'] ?? ''));
        if ($riskLevel === null) {
            throw new AiRequestException('The AI review returned an invalid risk_level.');
        }

        $recommendation = AiRecommendation::tryFrom((string) ($data['recommendation'] ?? ''));
        if ($recommendation === null) {
            throw new AiRequestException('The AI review returned an invalid recommendation.');
        }

        $summary = trim((string) ($data['summary'] ?? ''));
        if ($summary === '') {
            throw new AiRequestException('The AI review returned an empty summary.');
        }

        $concerns = collect($data['concerns'] ?? [])
            ->map(fn (mixed $concern): string => trim((string) $concern))
            ->filter(fn (string $concern): bool => $concern !== '')
            ->values()
            ->all();

        return new self(
            riskLevel: $riskLevel,
            recommendation: $recommendation,
            summary: $summary,
            concerns: $concerns,
            rawResponse: $rawResponse,
        );
    }
}
