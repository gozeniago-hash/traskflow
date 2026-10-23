<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDoc>
 */
class ProjectDocFactory extends Factory
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
            'title' => fake()->sentence(3),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'version' => 0,
            'position' => fn (array $attributes) => (int) ProjectDoc::where('project_id', $attributes['project_id'])->max('position') + 1,
        ];
    }

    public function writtenBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['created_by' => $user->id, 'updated_by' => $user->id]);
    }

    public function sharedWithClient(): static
    {
        return $this->state(fn (array $attributes) => ['client_visible' => true]);
    }
}
