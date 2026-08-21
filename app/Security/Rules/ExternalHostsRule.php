<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects downloads or connections to external hosts in scripts. Exfiltrating
 * data to an external server is a key way for malicious plugins to phone home.
 */
final class ExternalHostsRule extends Rule
{
    public function id(): string
    {
        return 'external_hosts';
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
                'pattern' => '/\b(?:curl|wget|nc|ncat|socat|ftp|scp|rsync|git\s+clone)\b[^\n;|&$]{0,200}\bhttps?:\/\//i',
                'description' => 'Downloads or connects to an external HTTP(S) host.',
            ],
            [
                'pattern' => '/\b(?:curl|wget)\b[^\n;|&$]{0,120}\b(?:bash|sh|zsh|python|perl|php)\s+-/i',
                'description' => 'Network download combined with a script interpreter.',
            ],
            [
                'pattern' => '/\b(?:curl|wget|nc|ncat)\b[^\n;|&$]{0,120}--(?:no-check-certificate|insecure)\b/i',
                'description' => 'Insecure / certificate-validation-disabled download.',
            ],
        ]);
    }
}
