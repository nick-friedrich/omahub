<?php

namespace App\Security;

use App\Enums\RiskLevel;

interface SecurityRule
{
    public function id(): string;

    public function severity(): RiskLevel;

    /**
     * Whether this rule should inspect a file, given its relative path inside
     * the repository (e.g. "install.sh" or "src/Widget.qml").
     */
    public function matchesFile(string $relativePath): bool;

    /**
     * Inspect file contents and return zero or more findings.
     *
     * @return RuleFinding[]
     */
    public function inspect(string $relativePath, string $contents): array;
}
