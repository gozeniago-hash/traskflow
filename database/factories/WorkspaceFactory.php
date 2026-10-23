<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
        ];
    }

    /** Everyone inside, except clients, must turn on two-step verification. */
    public function requiringTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => ['require_two_factor' => true]);
    }

    /** Connected to GitHub with a stored access token. */
    public function connectedToGitHub(): static
    {
        return $this->state(fn (array $attributes) => ['github_token' => 'ghp_test_token', 'github_login' => 'octocat']);
    }
}
