<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A row for a file; tests that read the file itself store it with Storage::fake().
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
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
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }

    /** Attached to a task, and so to its project. */
    public function onTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => ['project_id' => $task->project_id, 'task_id' => $task->id]);
    }

    public function uploadedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }

    public function sharedWithClient(): static
    {
        return $this->state(fn (array $attributes) => ['client_visible' => true]);
    }

    public function sized(int $bytes): static
    {
        return $this->state(fn (array $attributes) => ['size' => $bytes]);
    }

    /** Given a title, description, group and extra details by the team. */
    public function withDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'group' => 'Contracts',
            'meta' => [['label' => 'Approved by', 'value' => fake()->name()]],
        ]);
    }
}
