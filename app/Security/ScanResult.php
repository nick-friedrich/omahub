<?php

namespace App\Security;

use App\Enums\RiskLevel;
use JsonSerializable;

/**
 * The outcome of a deterministic scan: the aggregate risk level plus every
 * finding. This is the serialization contract exchanged with the sandbox.
 */
final class ScanResult implements JsonSerializable
{
    /**
     * @param  RuleFinding[]  $findings
     * @param  string[]  $rulesRun
     */
    public function __construct(
        public RiskLevel $riskLevel,
        public array $findings,
        public array $rulesRun,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $findings = array_map(
            fn (array $finding): RuleFinding => RuleFinding::fromArray($finding),
            $data['findings'] ?? [],
        );

        $riskLevel = RiskLevel::tryFrom((string) ($data['risk_level'] ?? '')) ?? RiskLevel::None;

        return new self(
            riskLevel: $riskLevel,
            findings: $findings,
            rulesRun: array_map('strval', $data['rules_run'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'risk_level' => $this->riskLevel->value,
            'rules_run' => $this->rulesRun,
            'findings' => array_map(
                fn (RuleFinding $finding): array => $finding->toArray(),
                $this->findings,
            ),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
