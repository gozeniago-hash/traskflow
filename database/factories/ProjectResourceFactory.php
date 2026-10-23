<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A key link on the project overview; project_id isn't fillable, so pass the project with for().
 *
 * @extends Factory<ProjectResource>
 */
class ProjectResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->words(3, true),
            'url' => fake()->url(),
            'position' => fn (array $attributes) => (int) ProjectResource::where('project_id', $attributes['project_id'])->max('position') + 1,
        ];
    }

    public function sharedWithClient(): static
    {
        return $this->state(fn (array $attributes) => ['client_visible' => true]);
    }
}
