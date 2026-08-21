<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects permission and ownership changes, especially setuid/setgid or
 * otherwise escalating filesystem permissions.
 */
final class PermissionOwnershipRule extends Rule
{
    public function id(): string
    {
        return 'permission_ownership';
    }

    public function severity(): RiskLevel
    {
        return RiskLevel::High;
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
                'pattern' => '/\bchmod\b[^\n;|&$]{0,60}(?:4[0-7]{2}7|2[0-7]7|6[0-7]7|1[0-7]77|[0-7]077)/',
                'description' => 'Sets setuid/setgid/sticky or world-writable permissions.',
            ],
            [
                'pattern' => '/\bchmod\s+\+[sS]\b/i',
                'description' => 'Sets the setuid or setgid bit.',
            ],
            [
                'pattern' => '/\bchown\s+-R\b/i',
                'description' => 'Recursively changes file ownership.',
            ],
        ]);
    }
}
