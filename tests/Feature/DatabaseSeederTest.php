<?php

namespace Tests\Feature;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_categories_tags_and_only_the_real_plugin(): void
    {
        $this->seed();

        $this->assertDatabaseCount('categories', 4);
        $this->assertDatabaseCount('tags', 4);

        // No demo/factory-fake plugins; exactly one real, published plugin.
        $this->assertDatabaseCount('plugins', 1);
        $this->assertDatabaseHas('plugins', [
            'slug' => 'io.github.nick-friedrich.hyprland-dock',
            'repository_name' => 'hyprland-dock',
            'status' => 'published',
        ]);
    }

    public function test_seeder_restores_the_manifest_and_readme_fixtures(): void
    {
        $this->seed();

        $plugin = Plugin::query()->firstOrFail();

        $this->assertSame(
            json_decode(
                file_get_contents(database_path('seeders/fixtures/hyprland-dock/manifest.json')),
                true,
            ),
            $plugin->manifest_data,
        );

        $this->assertSame(
            file_get_contents(database_path('seeders/fixtures/hyprland-dock/README.md')),
            $plugin->readme_markdown,
        );
    }
}
