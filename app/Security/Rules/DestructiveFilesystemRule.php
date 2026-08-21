<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects commands that can destroy data on the host: recursive deletes on
 * absolute/variable paths, disk writes, or low-level filesystem manipulation.
 */
final class DestructiveFilesystemRule extends Rule
{
    public function id(): string
    {
        return 'destructive_filesystem';
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
                'pattern' => '/\brm\s+(?:-?\w+\s+)*-[a-zA-Z]*r[a-zA-Z]*\s+(?:\/[^\s]+|\$\{[^}]+\})/m',
                'description' => 'Recursive delete targeting an absolute or variable path.',
            ],
            [
                // `dd` is only a disk-write when it targets an output operand;
                // bare `dd` is also a common date-format token (e.g. "dd MMM").
                'pattern' => '/\b(?:mkfs\.\w+|mkswap|fdisk|parted|shred|badblocks|sfdisk)\b[^\n;|&$]{0,120}|\bdd\b[^\n;|&$]{0,120}\bof\s*=/i',
                'description' => 'Low-level disk manipulation or write command.',
            ],
            [
                'pattern' => '/\b(?:mv|rm|dd)\b[^\n;|&$]{0,60}\$\{(?:HOME|USER|SHELL|PWD)\}/',
                'description' => 'Filesystem operation targeting an environment-variable path.',
            ],
            [
                'pattern' => '/\b(?:rm\s+-rf\s*\/|[^\w-]\/dev\/sd[a-z]+[0-9]?)/',
                'description' => 'Destructive operation on the root filesystem or a block device.',
            ],
        ]);
    }
}
