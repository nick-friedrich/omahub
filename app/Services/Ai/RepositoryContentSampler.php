<?php

namespace App\Services\Ai;

use FilesystemIterator;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Extracts an untrusted repository tarball (never on a production host — the
 * caller must already be in the scan sandbox context) and collects a bounded
 * sample of its text files for an AI review prompt.
 *
 * Manifest and README files are prioritized, then common script/config files,
 * then everything else. Files are truncated by line count so the prompt stays
 * within the model's context window.
 */
final class RepositoryContentSampler
{
    private const SKIPPED_DIRECTORIES = [
        '.git', 'node_modules', '.venv', 'vendor', 'dist', 'build', '.next', '__pycache__',
    ];

    private const PRIORITY_EXTENSIONS = [
        'sh', 'bash', 'zsh', 'fish', 'qml', 'py', 'js', 'ts', 'pl', 'rb', 'lua',
        'toml', 'yaml', 'yml', 'conf', 'cfg', 'ini', 'txt', 'env', 'service', 'timer',
    ];

    public function __construct(
        private readonly int $maxFiles,
        private readonly int $maxLines,
    ) {}

    /**
     * @return array<int, array{path: string, contents: string}>
     */
    public function sample(string $tarball): array
    {
        if ($tarball === '') {
            throw new RuntimeException('Received an empty repository archive to sample.');
        }

        $tempDir = $this->makeTempDir();
        $extractDir = $tempDir.'/extracted';
        mkdir($extractDir, 0777, true);

        try {
            $this->extract($tarball, $tempDir, $extractDir);

            return $this->collect($extractDir);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /** @return array<int, array{path: string, contents: string}> */
    private function collect(string $root): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        $candidates = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relative = $this->relativePath($root, $file->getPathname());

            if (! $this->shouldInclude($relative) || $file->getSize() > 2 * 1024 * 1024) {
                continue;
            }

            $candidates[] = [
                'file' => $file,
                'path' => $relative,
                'priority' => $this->priority($relative),
            ];
        }

        usort(
            $candidates,
            fn (array $a, array $b): int => ($a['priority'] <=> $b['priority']) ?: strcmp($a['path'], $b['path']),
        );

        $sampled = [];

        foreach (array_slice($candidates, 0, $this->maxFiles) as $candidate) {
            /** @var SplFileInfo $file */
            $file = $candidate['file'];
            $contents = @file_get_contents($file->getPathname());

            if ($contents === false || str_contains($contents, "\0")) {
                continue;
            }

            $sampled[] = [
                'path' => $candidate['path'],
                'contents' => $this->truncate($contents),
            ];
        }

        return $sampled;
    }

    private function priority(string $relativePath): int
    {
        $basename = strtolower(basename($relativePath));

        if ($basename === 'manifest.json' || str_starts_with($basename, 'readme')) {
            return 0;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return in_array($extension, self::PRIORITY_EXTENSIONS, true) ? 1 : 2;
    }

    private function truncate(string $contents): string
    {
        $lines = preg_split('/\R/', $contents) ?: [];

        $kept = [];
        foreach (array_slice($lines, 0, $this->maxLines) as $line) {
            $kept[] = mb_substr(rtrim($line, "\r"), 0, 200);
        }

        return implode("\n", $kept);
    }

    private function shouldInclude(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        foreach (self::SKIPPED_DIRECTORIES as $dir) {
            if (str_contains($normalized, "/{$dir}/")) {
                return false;
            }
        }

        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

        return ! in_array($extension, [
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp', 'avif',
            'mp3', 'mp4', 'wav', 'ogg', 'flac', 'm4a', 'webm', 'mkv', 'mov',
            'zip', 'gz', 'tar', 'bz2', '7z', 'xz', 'rar',
            'pdf', 'exe', 'dll', 'so', 'o', 'a', 'class', 'jar', 'pyc',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            'db', 'sqlite', 'lock',
        ], true);
    }

    private function extract(string $tarball, string $tempDir, string $extractDir): void
    {
        $archive = $tempDir.'/archive.tar.gz';
        file_put_contents($archive, $tarball);

        $phar = new PharData($archive);

        try {
            $phar->extractTo($extractDir, null, true);
        } catch (Throwable) {
            $tar = $phar->decompress();
            $tar->extractTo($extractDir, null, true);
        }
    }

    private function relativePath(string $base, string $path): string
    {
        $base = rtrim($base, '/');
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base.'/') ? substr($path, strlen($base) + 1) : basename($path);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/omahub-sample-'.bin2hex(random_bytes(8));

        if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create a temporary sample directory at {$dir}.");
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
