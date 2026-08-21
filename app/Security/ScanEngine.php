<?php

namespace App\Security;

use App\Enums\RiskLevel;
use FilesystemIterator;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Applies the deterministic rule set to an untrusted repository tarball.
 *
 * The scan itself is pure static analysis (regex over file contents), but it
 * must be *executed* against untrusted content — extract and read the tarball
 * inside a sandbox (see SandboxRunner), never directly on the host.
 */
final class ScanEngine
{
    private const BINARY_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp', 'avif',
        'mp3', 'mp4', 'wav', 'ogg', 'flac', 'm4a', 'webm', 'mkv', 'mov',
        'zip', 'gz', 'tar', 'bz2', '7z', 'xz', 'rar',
        'pdf', 'exe', 'dll', 'so', 'o', 'a', 'class', 'jar', 'pyc',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'db', 'sqlite', 'lock',
    ];

    private const SKIPPED_DIRECTORIES = [
        '.git', 'node_modules', '.venv', 'vendor', 'dist', 'build', '.next', '__pycache__',
    ];

    /** @param  list<SecurityRule>  $rules */
    public function __construct(
        private readonly array $rules,
        private readonly int $maxFileSize,
        private readonly int $maxFiles,
    ) {}

    public function scanTarball(string $tarball): ScanResult
    {
        if ($tarball === '') {
            throw new RuntimeException('Received an empty repository archive to scan.');
        }

        $tempDir = $this->makeTempDir();
        $extractDir = $tempDir.'/extracted';
        mkdir($extractDir, 0777, true);

        try {
            $this->extract($tarball, $tempDir, $extractDir);

            return $this->scanDirectory($extractDir);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function scanDirectory(string $directory): ScanResult
    {
        if (! is_dir($directory)) {
            throw new RuntimeException("Cannot scan directory {$directory}: not found.");
        }

        $findings = [];
        $rulesRun = [];
        $count = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if (++$count > $this->maxFiles) {
                break;
            }

            $relative = $this->relativePath($directory, $file->getPathname());

            if ($file->getSize() > $this->maxFileSize || ! $this->shouldScan($relative)) {
                continue;
            }

            $contents = @file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            // Rough binary detection: rule patterns are text-oriented.
            if (str_contains($contents, "\0")) {
                continue;
            }

            foreach ($this->rules as $rule) {
                if (! $rule->matchesFile($relative)) {
                    continue;
                }

                $rulesRun[$rule->id()] = true;

                foreach ($rule->inspect($relative, $contents) as $finding) {
                    $findings[] = $finding;
                }
            }
        }

        // Overlapping rule patterns can match the same line twice; keep a single
        // finding per (rule, file, line).
        $unique = [];
        foreach ($findings as $finding) {
            $key = $finding->rule."\0".$finding->file."\0".$finding->line;
            $unique[$key] = $finding;
        }
        $findings = array_values($unique);

        // Documentation files (README, docs/, *.md) are descriptive, not
        // executable code. A `curl | sh` block in a README is a usage example,
        // so documentation findings are reported but never determine "high"
        // risk: the level reflects executable code, and a scan whose only
        // findings are documentation is capped at Low.
        $severity = fn (RuleFinding $finding): RiskLevel => RiskLevel::tryFrom($finding->severity) ?? RiskLevel::None;
        $codeFindings = array_values(array_filter(
            $findings,
            fn (RuleFinding $finding): bool => ! DocumentationFile::matches($finding->file),
        ));
        $docFindings = array_values(array_filter(
            $findings,
            fn (RuleFinding $finding): bool => DocumentationFile::matches($finding->file),
        ));

        $riskLevel = match (true) {
            $codeFindings !== [] => RiskLevel::aggregate(array_map($severity, $codeFindings)),
            $docFindings !== [] => RiskLevel::Low,
            default => RiskLevel::None,
        };

        return new ScanResult($riskLevel, $findings, array_keys($rulesRun));
    }

    private function extract(string $tarball, string $tempDir, string $extractDir): void
    {
        $archive = $tempDir.'/archive.tar.gz';
        file_put_contents($archive, $tarball);

        $phar = new PharData($archive);

        try {
            $phar->extractTo($extractDir, null, true);
        } catch (Throwable) {
            // Fall back to decompressing the gzip layer first on older SAPIs.
            $tar = $phar->decompress();
            $tar->extractTo($extractDir, null, true);
        }
    }

    private function shouldScan(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        foreach (self::SKIPPED_DIRECTORIES as $dir) {
            if (str_contains($normalized, "/{$dir}/")) {
                return false;
            }
        }

        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

        return ! in_array($extension, self::BINARY_EXTENSIONS, true);
    }

    private function relativePath(string $base, string $path): string
    {
        $base = rtrim($base, '/');
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base.'/') ? substr($path, strlen($base) + 1) : basename($path);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/omahub-scan-'.bin2hex(random_bytes(8));

        if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create a temporary scan directory at {$dir}.");
        }

        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item instanceof SplFileInfo
                ? ($item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()))
                : null;
        }

        @rmdir($dir);
    }
}
