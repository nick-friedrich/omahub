<?php

namespace Tests\Concerns;

use Phar;

trait BuildsTarballs
{
    /**
     * Build a `.tar.gz` byte string from a directory on disk. Directory
     * entries are rooted at a single top-level folder (like a GitHub tarball).
     */
    private function tarballFromDirectory(string $directory): string
    {
        $tempDir = sys_get_temp_dir().'/omahub-test-'.bin2hex(random_bytes(6));
        mkdir($tempDir);

        $tarPath = $tempDir.'/archive.tar';
        $phar = new \PharData($tarPath);
        $phar->buildFromDirectory($directory);

        $gz = $phar->compress(Phar::GZ);
        $bytes = file_get_contents($gz->getPath()) ?: '';

        $this->removeDirectory($tempDir);

        return $bytes;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
