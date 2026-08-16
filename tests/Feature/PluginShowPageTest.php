<?php

namespace Tests\Feature;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_page_renders_tables_and_rewrites_relative_images(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'readme_markdown' => "# Intro\n\n![preview](preview.png)\n\n| A | B |\n| --- | --- |\n| 1 | 2 |",
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('<h1>Intro</h1>', escape: false)
            ->assertSee('<table>', escape: false)
            ->assertSee(
                "https://raw.githubusercontent.com/{$plugin->repository_owner}/{$plugin->repository_name}/main/preview.png",
                escape: false,
            );
    }

    public function test_detail_page_serves_an_escaped_readme_without_a_repository_identity(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'default_branch' => null,
            'readme_markdown' => '![x](local.png) <script>bad()</script>',
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('src="local.png"', escape: false)
            ->assertDontSee('<script>', escape: false);
    }
}
