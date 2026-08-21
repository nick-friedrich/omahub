<?php

namespace App\Security\Rules;

use App\Enums\RiskLevel;
use App\Security\Rule;
use App\Security\RuleFinding;

/**
 * Detects decode-and-execute patterns: content is base64/hex/openssl-decoded
 * and then handed to a shell or interpreter, which hides what actually runs.
 */
final class DecodeAndExecuteRule extends Rule
{
    public function id(): string
    {
        return 'decode_and_execute';
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
                'pattern' => '/\b(?:echo|printf|cat)\s+["\']?[^"\';\n]*["\']?\s*\|\s*base64\s*-?d?\s*\|\s*(?:sh|bash|zsh|dash)\b/i',
                'description' => 'Base64-decoded content is piped into a shell.',
            ],
            [
                'pattern' => '/\bbase64\s*-?d?[^\n;]*\s*\|\s*(?:sh|bash|zsh|dash)\b/i',
                'description' => 'Base64-decoded content is piped into a shell.',
            ],
            [
                'pattern' => '/\b(?:python|python3|php|perl|ruby)\s+-c\s+["\'][^"\']*(?:exec|eval|system|os\.system|popen|shell_exec)/i',
                'description' => 'Interpreter evaluated with an execution builtin.',
            ],
            [
                'pattern' => '/\beval\s*\(\s*(?:echo|printf|base64|cat|\(\$)/i',
                'description' => 'eval() applied to shell-substitution or decoded output.',
            ],
            [
                'pattern' => '/\b[a-zA-Z0-9+\/=]{120,}\s*\|\s*(?:base64|xxd)\s*-\s*d\s*(?:\|)?\s*(?:sh|bash)/',
                'description' => 'Large opaque blob is decoded and executed via shell.',
            ],
        ]);
    }
}
