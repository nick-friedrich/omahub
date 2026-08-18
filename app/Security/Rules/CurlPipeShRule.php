<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects shell commands that download a script and execute it immediately,
 * e.g. `curl … | sh` or `wget … | bash`. The downloaded payload is opaque to
 * static analysis, which makes this a common obfuscation pattern.
 */
final class CurlPipeShRule extends Rule
{
    public function id(): string
    {
        return 'curl_pipe_sh';
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
                'pattern' => '/\b(?:(?:curl|wget)\b[^\n;|&$]{0,200}\|\s*(?:sh|bash|zsh|dash)\b|\b(?:sh|bash|zsh|dash)\s+\-\s*<\/?\s*(?:curl|wget))/i',
                'description' => 'Command downloads content and pipes it directly into a shell interpreter.',
            ],
            [
                'pattern' => '/\bcurl[^\n;|&$]{0,100}\|\s*(?:sudo\s+)?(?:sh|bash|zsh|dash)\b/i',
                'description' => 'curl output is executed by a shell (curl | sh pattern).',
            ],
        ]);
    }
}
