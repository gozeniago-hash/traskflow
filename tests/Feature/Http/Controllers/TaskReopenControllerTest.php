<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Undo" on the "Marked complete" toast. */
class TaskReopenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reopening_a_task_that_is_already_open_changes_nothing(): void
    {
        $workspace = Workspace::factory()->create();
        $task = Task::factory()->for(Project::factory()->for($workspace))->create();

        $this->actingAs(User::factory()->inWorkspace($workspace)->create())
            ->postJson(route('tasks.reopen', $task))
            ->assertOk();

        $this->assertNull($task->fresh()->completed_at);
        $this->assertDatabaseMissing('activities', ['task_id' => $task->id, 'action' => 'reopened']);
    }
}
