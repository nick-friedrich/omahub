<?php

namespace Database\Seeders;

use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = collect(['Desktop', 'Development', 'Media', 'System'])->mapWithKeys(
            fn (string $name) => [$name => Category::query()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ])],
        );

        $tags = collect(['Hyprland', 'Waybar', 'Terminal', 'Productivity'])->mapWithKeys(
            fn (string $name) => [$name => Tag::query()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ])],
        );

        // Seed the registry's real, already-published plugin rather than demo data.
        $this->seedPublishedPlugin();
    }

    private function seedPublishedPlugin(): void
    {
        $fixture = database_path('seeders/fixtures/hyprland-dock');

        $manifest = json_decode(
            (string) file_get_contents("{$fixture}/manifest.json"),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        Plugin::query()->create([
            'slug' => 'io.github.nick-friedrich.hyprland-dock',
            'name' => 'Hyprland Dock',
            'description' => 'A configurable macOS-inspired application dock for Hyprland.',
            'repository_url' => 'https://github.com/nick-friedrich/hyprland-dock',
            'repository_owner' => 'nick-friedrich',
            'repository_name' => 'hyprland-dock',
            'author_name' => 'Nick Friedrich',
            'author_url' => 'https://github.com/nick-friedrich',
            'license' => 'MIT',
            'homepage_url' => null,
            'icon_url' => null,
            'manifest_data' => $manifest,
            'readme_markdown' => (string) file_get_contents("{$fixture}/README.md"),
            'default_branch' => 'master',
            'latest_commit_sha' => '2725296a28123dfb16aab2ad9127f1f3abf3eca3',
            'latest_version' => '1.0.0',
            'stars_count' => 0,
            'forks_count' => 0,
            'open_issues_count' => 0,
            'is_archived' => false,
            'last_pushed_at' => now(),
            'last_indexed_at' => now(),
            'published_at' => now(),
            'status' => PluginStatus::Published,
        ]);
    }
}
