<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\PluginSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PluginSubmission> */
class PluginSubmissionFactory extends Factory
{
    public function definition(): array
    {
        $owner = fake()->userName();
        $repository = fake()->slug(2);

        return [
            'repository_url' => "https://github.com/{$owner}/{$repository}",
            'status' => SubmissionStatus::Pending,
            'submitted_at' => now(),
        ];
    }
}
