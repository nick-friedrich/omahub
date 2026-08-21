<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects use of `sudo`, which escalates privileges beyond the plugin's user.
 */
final class SudoRule extends Rule
{
    public function id(): string
    {
        return 'sudo';
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
                'pattern' => '/\bsudo(?: -[A-Za-z]+)*\s+(?:-i|su|passwd|useradd|groupadd|usermod|visudo|tee)\b/i',
                'description' => 'Privilege escalation via sudo targeting system accounts or configuration.',
            ],
            [
                'pattern' => '/\bsudo\s+(?!-u\b|chown|chmod|ls|cat|test|true|false)[a-z][a-z0-9_-]{0,40}(?:\s|$)/i',
                'description' => 'Command runs with sudo, elevating the process beyond the plugin environment.',
            ],
        ]);
    }
}
