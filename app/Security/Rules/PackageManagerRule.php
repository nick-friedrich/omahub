<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects package managers modifying the system: apt, dnf, pacman, snap,
 * system-wide pip/npm installs, or kernel/firmware operations. Application
 * plugins should not install system packages.
 */
final class PackageManagerRule extends Rule
{
    public function id(): string
    {
        return 'package_manager';
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
                'pattern' => '/\b(?:apt-get|apt|dnf|yum|pacman|zypper|emerge)\s+(?:update|install|remove|upgrade|autoremove|reinstall)\b/i',
                'description' => 'System package manager operation.',
            ],
            [
                'pattern' => '/\bsnap\s+(?:install|remove|refresh)\b/i',
                'description' => 'Snap package installation or removal.',
            ],
            [
                'pattern' => '/\b(?:pip|pip3|pipx)\s+install\b(?!.*--user)/i',
                'description' => 'System-wide Python package installation (not --user).',
            ],
            [
                'pattern' => '/\bnpm\s+(?:i|install)\s+-g\b/i',
                'description' => 'Global npm package installation.',
            ],
        ]);
    }
}
