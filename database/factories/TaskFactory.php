<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tasks get their ticket number from the project when they are created.
 *
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'title' => fake()->sentence(4),
            'position' => 1,
        ];
    }

    /** Placed in a section, and so in that section's project. */
    public function inSection(Section $section): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $section->project_id,
            'section_id' => $section->id,
            'position' => (int) Task::where('section_id', $section->id)->whereNull('parent_id')->max('position') + 1,
        ]);
    }

    /** A subtask of another task. */
    public function subtaskOf(Task $parent): static
    {
        return $this->state(fn (array $attributes) => ['project_id' => $parent->project_id, 'parent_id' => $parent->id, 'section_id' => null]);
    }

    public function createdBy(User $creator): static
    {
        return $this->state(fn (array $attributes) => ['creator_id' => $creator->id]);
    }

    public function assignedTo(User $assignee): static
    {
        return $this->state(fn (array $attributes) => ['assignee_id' => $assignee->id]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['completed_at' => now()]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn (array $attributes) => ['due_date' => $date]);
    }

    /** Shown to the client in the portal. */
    public function sharedWithClient(): static
    {
        return $this->state(fn (array $attributes) => ['client_visible' => true]);
    }

    /** Shared with the client and waiting for them to approve it. */
    public function awaitingApproval(): static
    {
        return $this->sharedWithClient()->state(fn (array $attributes) => ['approval_status' => 'pending']);
    }
}
