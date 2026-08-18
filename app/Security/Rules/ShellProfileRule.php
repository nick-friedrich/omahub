<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects modification of shell profiles or dotfiles, which can inject
 * commands into every future interactive session on the machine.
 */
final class ShellProfileRule extends Rule
{
    public function id(): string
    {
        return 'shell_profile';
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
                'pattern' => '/\b(?:echo|tee|cat|>>|>>?|printf)\b[^\n;|&$]{0,80}(?:\.bashrc|\.bash_profile|\.zshrc|\.profile|\.bash_aliases|\.pam_environment|\.zprofile|\.zlogin|\.login)\b/i',
                'description' => 'Appends or writes to a shell profile or session init file.',
            ],
            [
                'pattern' => '/\b(?:source|\.)\s+(?:~|\.)\/(?:\.bashrc|\.zshrc|\.profile|\.bash_profile)\b/i',
                'description' => 'Sourcing a user shell profile from a script.',
            ],
            [
                'pattern' => '/\b[\w:>]+\s*(?:>>|>>?)?\s*(?:~|\\$\{HOME\}|\\\$HOME)\/\.(?:bashrc|zshrc|profile|bash_profile)\b/',
                'description' => 'Redirects content into a home directory shell profile.',
            ],
        ]);
    }
}
