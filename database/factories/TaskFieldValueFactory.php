<?php

namespace Database\Factories;

use App\Models\ProjectField;
use App\Models\Task;
use App\Models\TaskFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskFieldValue>
 */
class TaskFieldValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'field_id' => fn (array $attributes) => ProjectField::factory()->create(['project_id' => Task::find($attributes['task_id'])->project_id])->id,
            'value' => fake()->word(),
        ];
    }
}
