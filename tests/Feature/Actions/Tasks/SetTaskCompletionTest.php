<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\SetTaskCompletion;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Completing and reopening a task sets the state the person asked for. */
class SetTaskCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_an_open_task_marks_it_complete_and_records_it(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();
        $this->freezeTime();

        $changed = app(SetTaskCompletion::class)->handle($task, $user, true);

        $this->assertTrue($changed);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed_by' => $user->id, 'completed_at' => now()]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'user_id' => $user->id, 'action' => 'completed']);
    }

    public function test_reopening_a_completed_task_clears_who_completed_it_and_records_it(): void
    {
        $task = Task::factory()->completed()->create();
        $user = User::factory()->create();

        $changed = app(SetTaskCompletion::class)->handle($task, $user, false);

        $this->assertTrue($changed);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed_at' => null, 'completed_by' => null]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'reopened']);
    }

    public function test_completing_a_task_someone_already_completed_changes_nothing(): void
    {
        $task = Task::factory()->create();
        $stalePage = Task::find($task->id);
        $first = User::factory()->create();
        $second = User::factory()->create();
        app(SetTaskCompletion::class)->handle($task, $first, true);

        $changed = app(SetTaskCompletion::class)->handle($stalePage, $second, true);

        $this->assertFalse($changed);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed_by' => $first->id]);
        $this->assertSame(1, Activity::where('task_id', $task->id)->where('action', 'completed')->count());
        $this->assertTrue($stalePage->isCompleted());
    }
}
