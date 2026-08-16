<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'github_id' => fake()->unique()->randomNumber(7),
            'github_username' => $username,
            'github_url' => "https://github.com/{$username}",
            'avatar_url' => 'https://avatars.githubusercontent.com/u/'.fake()->unique()->randomNumber(7),
            'is_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'is_admin' => true,
        ]);
    }
}
