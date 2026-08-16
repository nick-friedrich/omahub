<?php

namespace Database\Seeders;

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

        $plugins = collect([
            ['name' => 'Workspace Switcher', 'owner' => 'omarchy-labs', 'repo' => 'workspace-switcher'],
            ['name' => 'Now Playing', 'owner' => 'omarchy-labs', 'repo' => 'now-playing'],
            ['name' => 'Project Launcher', 'owner' => 'community-tools', 'repo' => 'project-launcher'],
        ])->map(function (array $plugin): Plugin {
            return Plugin::factory()->published()->create([
                'name' => $plugin['name'],
                'slug' => $plugin['repo'],
                'repository_url' => "https://github.com/{$plugin['owner']}/{$plugin['repo']}",
                'repository_owner' => $plugin['owner'],
                'repository_name' => $plugin['repo'],
                'author_name' => $plugin['owner'],
                'author_url' => "https://github.com/{$plugin['owner']}",
            ]);
        });

        $plugins[0]->categories()->attach($categories['Desktop']);
        $plugins[0]->tags()->attach([$tags['Hyprland']->id, $tags['Productivity']->id]);
        $plugins[1]->categories()->attach($categories['Media']);
        $plugins[1]->tags()->attach($tags['Waybar']);
        $plugins[2]->categories()->attach($categories['Development']);
        $plugins[2]->tags()->attach([$tags['Terminal']->id, $tags['Productivity']->id]);

        Plugin::factory()->count(2)->create();
    }
}
