<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects shell obfuscation: octal/hex escapes used to hide what command
 * actually runs from static analysis.
 */
final class ObfuscationRule extends Rule
{
    public function id(): string
    {
        return 'obfuscation';
    }

    public function severity(): RiskLevel
    {
        return RiskLevel::Low;
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
                'pattern' => '/\\\\(?:0[0-7]{2}|[0-7]{1,3}|x[0-9a-fA-F]{2})\$?[a-zA-Z_]/',
                'description' => 'Augments a command with octal/hex escape sequences.',
            ],
        ]);
    }
}
