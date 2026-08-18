<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects generic use of `eval`-style dynamic code execution.
 */
final class EvalRule extends Rule
{
    public function id(): string
    {
        return 'eval';
    }

    public function severity(): RiskLevel
    {
        return RiskLevel::Medium;
    }

    public function matchesFile(string $relativePath): bool
    {
        return true;
    }

    /** @return RuleFinding[] */
    public function inspect(string $relativePath, string $contents): array
    {
        return $this->inspectPatterns($relativePath, $contents, [
            [
                'pattern' => '/\beval\s*\(/i',
                'description' => 'Dynamic code execution via eval().',
            ],
            [
                'pattern' => '/\b(?:source|\.)\s+(?:<(?:\(|\\$)|\/dev\/|\\$\()/',
                'description' => 'Shell sources dynamically generated content.',
            ],
            [
                'pattern' => '/\b__import__["\']?(?:\s*\(|["\'])?(?:os|subprocess|pty)/i',
                'description' => 'Python dynamically imports process/pty modules.',
            ],
        ]);
    }
}
