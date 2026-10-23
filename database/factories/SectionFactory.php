<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
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
            'name' => fake()->randomElement(['To do', 'Doing', 'Review', 'Done']),
            'position' => fn (array $attributes) => (int) Section::where('project_id', $attributes['project_id'])->max('position') + 1,
        ];
    }
}
