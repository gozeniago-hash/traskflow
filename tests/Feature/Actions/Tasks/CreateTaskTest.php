<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\CreateTask;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Creating a task from any of the "add task" places. */
class CreateTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_task_goes_to_the_end_of_the_first_section_and_is_recorded(): void
    {
        $project = Project::factory()->create();
        $first = Section::factory()->for($project)->create();
        Section::factory()->for($project)->create();
        Task::factory()->inSection($first)->create();
        $user = User::factory()->create();

        $task = app(CreateTask::class)->handle($project, $user, ['title' => '  Write release notes  ']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'section_id' => $first->id, 'position' => 2, 'title' => 'Write release notes', 'creator_id' => $user->id]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'user_id' => $user->id, 'action' => 'created']);
    }

    public function test_assignee_becomes_a_project_member(): void
    {
        $project = Project::factory()->create();
        $assignee = User::factory()->create();

        app(CreateTask::class)->handle($project, User::factory()->create(), ['title' => 'Call the printer', 'assignee_id' => $assignee->id]);

        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $assignee->id]);
    }

    public function test_task_is_shared_with_the_client_only_when_the_portal_is_on(): void
    {
        $withPortal = Project::factory()->withPortal(Client::factory()->create())->create();
        $withoutPortal = Project::factory()->create();
        $user = User::factory()->create();

        $shared = app(CreateTask::class)->handle($withPortal, $user, ['title' => 'Shared', 'client_visible' => true]);
        $internal = app(CreateTask::class)->handle($withoutPortal, $user, ['title' => 'Internal', 'client_visible' => true]);

        $this->assertTrue($shared->fresh()->client_visible);
        $this->assertFalse($internal->fresh()->client_visible);
    }

    public function test_custom_field_values_are_saved_with_the_task(): void
    {
        $project = Project::factory()->create();
        $field = ProjectField::factory()->for($project)->create();

        $task = app(CreateTask::class)->handle($project, User::factory()->create(), ['title' => 'With a field'], [$field->id => 'Blue']);

        $this->assertDatabaseHas('task_field_values', ['task_id' => $task->id, 'field_id' => $field->id, 'value' => 'Blue']);
    }
}
