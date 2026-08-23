<?php

namespace Database\Factories;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plugin> */
class PluginFactory extends Factory
{
    public function definition(): array
    {
        $owner = fake()->unique()->userName();
        $repository = fake()->unique()->slug(2);
        $name = Str::headline($repository);
        $version = fake()->randomElement(['0.1.0', '1.0.0', '1.2.0', '2.0.1']);

        return [
            'slug' => Str::slug("{$owner}-{$repository}"),
            'name' => $name,
            'description' => fake()->sentence(),
            'repository_url' => "https://github.com/{$owner}/{$repository}",
            'repository_owner' => $owner,
            'repository_name' => $repository,
            'author_name' => $owner,
            'author_url' => "https://github.com/{$owner}",
            'license' => fake()->randomElement(['MIT', 'GPL-3.0', 'Apache-2.0']),
            'homepage_url' => null,
            'icon_url' => null,
            'manifest_data' => [
                'name' => $name,
                'version' => $version,
                'description' => fake()->sentence(),
            ],
            'default_branch' => 'main',
            'latest_commit_sha' => fake()->sha1(),
            'latest_version' => $version,
            'stars_count' => fake()->numberBetween(0, 2500),
            'forks_count' => fake()->numberBetween(0, 200),
            'open_issues_count' => fake()->numberBetween(0, 30),
            'is_archived' => false,
            'last_pushed_at' => fake()->dateTimeBetween('-1 year'),
            'last_indexed_at' => now(),
            'published_at' => null,
            'status' => PluginStatus::Pending,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PluginStatus::Published,
            'published_at' => fake()->dateTimeBetween('-6 months'),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => PluginStatus::Archived,
            'is_archived' => true,
        ]);
    }

    public function repositoryRemoved(): static
    {
        return $this->state(fn () => [
            'status' => PluginStatus::Archived,
            'repository_removed_at' => now(),
            'published_at' => fake()->dateTimeBetween('-6 months'),
        ]);
    }
}
