<?php

namespace Tests\Unit\Services;

use App\Services\Ai\RepositoryContentSampler;
use Tests\Concerns\BuildsTarballs;
use Tests\TestCase;

class RepositoryContentSamplerTest extends TestCase
{
    use BuildsTarballs;

    private function sampler(int $maxFiles = 40, int $maxLines = 200): RepositoryContentSampler
    {
        return new RepositoryContentSampler($maxFiles, $maxLines);
    }

    public function test_it_samples_files_with_paths_and_contents(): void
    {
        $tarball = $this->tarballFromDirectory(base_path('tests/Fixtures/security/malicious'));

        $sample = $this->sampler()->sample($tarball);

        $paths = array_column($sample, 'path');
        $this->assertContains('setup.sh', $paths);
        $this->assertContains('persist.sh', $paths);

        $setup = collect($sample)->firstWhere('path', 'setup.sh');
        $this->assertNotNull($setup);
        $this->assertStringContainsString('sudo', (string) $setup['contents']);
    }

    public function test_manifest_and_readme_are_prioritized_first(): void
    {
        $tarball = $this->tarballFromDirectory(base_path('tests/Fixtures/security/clean'));

        $sample = $this->sampler()->sample($tarball);

        $paths = array_column($sample, 'path');
        // README and manifest-like files sort ahead of source files.
        $this->assertContains('README.md', $paths);
        $this->assertLessThan(
            array_search('src/Widget.qml', $paths, true),
            array_search('README.md', $paths, true),
        );
    }

    public function test_it_truncates_long_files_by_line_count(): void
    {
        $dir = sys_get_temp_dir().'/omahub-sample-test-'.bin2hex(random_bytes(4));
        mkdir("{$dir}/repo-root", 0777, true);
        file_put_contents("{$dir}/repo-root/long.sh", implode("\n", range(1, 500)));

        $sample = $this->sampler(maxFiles: 40, maxLines: 50)->sample($this->tarballFromDirectory("{$dir}/repo-root"));

        $this->assertCount(1, $sample);
        $this->assertSame(50, substr_count($sample[0]['contents'], "\n") + 1);

        $this->removeDirectory($dir);
    }
}
