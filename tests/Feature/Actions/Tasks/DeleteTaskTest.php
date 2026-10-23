<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\DeleteTask;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Deleting a task takes its subtasks and files with it, and Undo brings them all back. */
class DeleteTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_task_removes_its_subtasks_and_files(): void
    {
        Storage::fake('local');
        $task = Task::factory()->create();
        $subtask = Task::factory()->subtaskOf($task)->create();
        $file = Attachment::factory()->onTask($task)->create();
        Storage::disk('local')->put($file->path, 'pdf');

        $undo = app(DeleteTask::class)->handle($task, User::factory()->create());

        $this->assertNotSame('', $undo);
        $this->assertModelMissing($task);
        $this->assertModelMissing($subtask);
        $this->assertModelMissing($file);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_undo_restores_the_task_with_its_subtasks_and_files(): void
    {
        Storage::fake('local');
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for(Project::factory()->for($workspace))->createdBy($user)->create();
        $subtask = Task::factory()->subtaskOf($task)->create();
        $file = Attachment::factory()->onTask($task)->create();
        $undo = app(DeleteTask::class)->handle($task, $user);

        $this->actingAs($user)->postJson(route('undo', $undo))->assertOk();

        $this->assertModelExists($task);
        $this->assertModelExists($subtask);
        $this->assertModelExists($file);
    }
}
