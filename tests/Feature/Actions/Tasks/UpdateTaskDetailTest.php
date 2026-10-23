<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\UpdateTaskDetail;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Details edited in the task panel are saved and show up in the task's history. */
class UpdateTaskDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_the_due_date_saves_it_and_records_the_new_date(): void
    {
        $task = Task::factory()->create();

        app(UpdateTaskDetail::class)->handle($task, User::factory()->create(), 'due_date', '2026-11-05');

        $this->assertSame('2026-11-05', $task->fresh()->due_date->toDateString());
        $this->assertSame(['to' => 'Nov 5'], Activity::where('task_id', $task->id)->where('action', 'due_changed')->first()->meta);
    }

    public function test_assigning_someone_makes_them_a_project_member_and_records_it(): void
    {
        $task = Task::factory()->create();
        $assignee = User::factory()->create(['name' => 'Rae Kim']);

        app(UpdateTaskDetail::class)->handle($task, User::factory()->create(), 'assignee_id', $assignee->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assignee_id' => $assignee->id]);
        $this->assertDatabaseHas('project_user', ['project_id' => $task->project_id, 'user_id' => $assignee->id]);
        $this->assertSame(['to' => 'Rae Kim'], Activity::where('task_id', $task->id)->where('action', 'assigned')->first()->meta);
    }

    public function test_moving_to_another_section_puts_the_task_at_its_end_and_records_it(): void
    {
        $project = Project::factory()->create();
        $from = Section::factory()->for($project)->create();
        $to = Section::factory()->for($project)->create(['name' => 'Done']);
        Task::factory()->inSection($to)->create();
        Task::factory()->inSection($to)->create();
        $task = Task::factory()->inSection($from)->create();

        app(UpdateTaskDetail::class)->handle($task, User::factory()->create(), 'section_id', $to);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'section_id' => $to->id, 'position' => 3]);
        $this->assertSame(['to' => 'Done'], Activity::where('task_id', $task->id)->where('action', 'moved')->first()->meta);
    }
}
