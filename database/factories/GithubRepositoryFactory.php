<?php

namespace Database\Factories;

use App\Models\GithubRepository;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * workspace_id isn't fillable, so pass the workspace with for().
 *
 * @extends Factory<GithubRepository>
 */
class GithubRepositoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'full_name' => 'acme/'.fake()->unique()->slug(2),
            'webhook_secret' => 'test-webhook-secret',
        ];
    }
}
