<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects access to credentials: SSH keys, cloud keys, password databases,
 * and secrets managers. Reading these from a plugin is never expected.
 */
final class CredentialAccessRule extends Rule
{
    public function id(): string
    {
        return 'credential_access';
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
                'pattern' => '/\b(?:cat|head|tail|cp|scp|read)\b[^\n;|&$]{0,80}(?:\.ssh|id_rsa|id_ed25519|id_dsa|authorized_keys)(?:\b|\/)/i',
                'description' => 'Reads or copies SSH private keys or authorized keys.',
            ],
            [
                'pattern' => '/\b(?:cat|grep|head|tail)\b[^\n;|&$]{0,80}(?:\.aws\/credentials|\.config\/gcloud|\.netrc|\.git-credentials)(?:\b|\/)/i',
                'description' => 'Reads cloud or stored credential files.',
            ],
            [
                'pattern' => '/\b(?:cat|grep)\b[^\n;|&$]{0,60}(?:\/etc\/shadow|\/etc\/passwd)\b/',
                'description' => 'Reads the system password database.',
            ],
            [
                'pattern' => '/\b(?:unset\s+HIST|history\s+-c)\b/i',
                'description' => 'Clears shell history, common in credential harvesting scripts.',
            ],
        ]);
    }
}
