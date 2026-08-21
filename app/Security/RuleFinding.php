<?php

namespace App\Security;

use JsonSerializable;

/**
 * A single deterministic finding produced by a rule. Serializes to the JSON
 * contract shared with the sandbox; serialized data always carries the rule
 * id and severity so findings can be reconstructed without re-running rules.
 */
final class RuleFinding implements JsonSerializable
{
    public function __construct(
        public string $rule,
        public string $severity,
        public string $file,
        public ?int $line,
        public ?string $snippet,
        public string $description,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            rule: (string) ($data['rule'] ?? ''),
            severity: (string) ($data['severity'] ?? 'unknown'),
            file: (string) ($data['file'] ?? ''),
            line: isset($data['line']) ? (int) $data['line'] : null,
            snippet: isset($data['snippet']) && is_string($data['snippet']) ? $data['snippet'] : null,
            description: (string) ($data['description'] ?? ''),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity,
            'file' => $this->file,
            'line' => $this->line,
            'snippet' => $this->snippet,
            'description' => $this->description,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
