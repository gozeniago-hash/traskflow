<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A finished hour of tracked time unless a state says otherwise.
 *
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
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
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_seconds' => 3600,
        ];
    }

    /** Tracked against a task, and so its project. */
    public function onTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => ['task_id' => $task->id, 'project_id' => $task->project_id]);
    }

    public function by(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }

    /** A timer that is still counting. */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => ['started_at' => now()->subMinutes(10), 'ended_at' => null, 'duration_seconds' => 0]);
    }
}
