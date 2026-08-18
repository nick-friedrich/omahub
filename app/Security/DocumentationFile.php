<?php

namespace App\Security;

/**
 * Classifies a repository-relative file path as documentation.
 *
 * Flags like a README's `curl | sh` install snippet are descriptive, not
 * executable code, so documentation findings are reported but must not drive
 * the whole-scan risk level (see ScanEngine).
 */
final class DocumentationFile
{
    public static function matches(string $relativePath): bool
    {
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));

        // Files under a docs/ (or doc/, documentation/) directory.
        if (preg_match('#(^|/)doc(?:s|umentation)/#i', $path) === 1) {
            return true;
        }

        $basename = basename($path);

        // Markdown-style extension, anywhere in the tree.
        if (preg_match('/\.(?:md|markdown|rst|txt)$/i', $basename) === 1) {
            return true;
        }

        // Well-known documentation file names (README, CHANGELOG, LICENSE, …),
        // with an optional documentation extension only — "install.sh" is code,
        // "INSTALL" or "INSTALL.md" is documentation.
        return preg_match('/^(?:README|CHANGELOG|LICENSE|COPYING|NOTICE|CONTRIBUTING|AUTHORS|HISTORY|INSTALL|HACKING)(?:\.(?:md|markdown|rst|txt))?$/i', $basename) === 1;
    }
}