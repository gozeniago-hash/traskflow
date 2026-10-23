<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A plain text field unless a state picks another type.
 *
 * @extends Factory<ProjectField>
 */
class ProjectFieldFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'type' => 'text',
            'position' => fn (array $attributes) => (int) ProjectField::where('project_id', $attributes['project_id'])->max('position') + 1,
        ];
    }

    /**
     * A dropdown with the given choices.
     *
     * @param array<int, string> $labels
     */
    public function select(array $labels = ['Low', 'High']): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'select', 'options' => ProjectField::makeOptions($labels)]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => ['required' => true]);
    }
}
