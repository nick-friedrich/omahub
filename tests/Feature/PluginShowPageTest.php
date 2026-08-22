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

    public function test_detail_page_uses_the_first_readme_image_for_social_previews(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'name' => 'Window Switcher',
            'description' => 'A faster way to switch windows.',
            'readme_markdown' => "# Intro\n\n![preview](screenshots/preview.png)",
        ]);

        $imageUrl = "https://raw.githubusercontent.com/{$plugin->repository_owner}/{$plugin->repository_name}/main/screenshots/preview.png";

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Window Switcher · Omahub">', escape: false)
            ->assertSee('<meta property="og:description" content="A faster way to switch windows.">', escape: false)
            ->assertSee('<meta property="og:image" content="'.$imageUrl.'">', escape: false)
            ->assertSee('<meta name="twitter:image" content="'.$imageUrl.'">', escape: false);
    }

    public function test_pages_without_a_plugin_preview_use_the_general_social_image(): void
    {
        $plugin = Plugin::factory()->published()->create([
            'readme_markdown' => '# No screenshots here',
        ]);

        $this->get("/plugins/{$plugin->slug}")
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', escape: false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false);
    }
}
