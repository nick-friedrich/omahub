<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects persistence mechanisms: systemd units, cron entries, autostart
 * files, desktop autostart, and init-service registration. Persistence keeps
 * a plugin running across reboots, beyond what a configuration overlay should.
 */
final class PersistenceRule extends Rule
{
    public function id(): string
    {
        return 'persistence';
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
                'pattern' => '/\b(?:crontab|systemctl\s+(?:enable|mask|disable)|systemd-run|install\s+[^\n;|&$]{0,40}\/etc\/systemd)\b/i',
                'description' => 'Registers scheduled or boot-time system tasks.',
            ],
            [
                'pattern' => '/\b(?:echo|tee|cat)\b[^\n;|&$]{0,60}\/etc\/(?:cron|crontab|systemd|rc\.|xdg)/i',
                'description' => 'Writes to system scheduling or boot configuration.',
            ],
            [
                'pattern' => '/\b(?:install|cp|ln)\b[^\n;|&$]{0,60}\.(?:desktop|service)\b[^\n;|&$]{0,60}(?:autostart|system\/)/i',
                'description' => 'Installs a desktop or system service for automatic startup.',
            ],
            [
                'pattern' => '/^\[Unit\]/m',
                'description' => 'Bundles a systemd unit file.',
            ],
        ]);
    }
}
