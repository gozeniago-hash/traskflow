<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
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
            'project_id' => fn (array $attributes) => Task::find($attributes['task_id'])->project_id,
            'user_id' => User::factory(),
            'kind' => 'task',
            'action' => 'created',
        ];
    }
}
