<?php

namespace Database\Factories;

use App\Models\GithubRepository;
use App\Models\PullRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open pull request; pass its repository with for().
 *
 * @extends Factory<PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_repository_id' => GithubRepository::factory(),
            'workspace_id' => fn (array $attributes) => GithubRepository::find($attributes['github_repository_id'])->workspace_id,
            'number' => fake()->unique()->numberBetween(1, 9999),
            'title' => fake()->sentence(4),
            'url' => fake()->url(),
            'state' => 'open',
        ];
    }
}
