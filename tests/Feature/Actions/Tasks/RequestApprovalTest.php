<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\RequestApproval;
use App\Enums\ApprovalStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Asking the client to approve a shared task. */
class RequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_asking_again_after_an_answer_clears_the_answer_and_records_the_request(): void
    {
        $client = User::factory()->create();
        $task = Task::factory()->sharedWithClient()->create(['approval_status' => ApprovalStatus::ChangesRequested, 'approval_by' => $client->id, 'approval_at' => now()]);

        app(RequestApproval::class)->handle($task, User::factory()->create());

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'approval_status' => 'pending', 'approval_by' => null, 'approval_at' => null]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'approval_requested']);
    }

    public function test_asking_while_a_request_is_already_pending_records_nothing(): void
    {
        $task = Task::factory()->awaitingApproval()->create();

        app(RequestApproval::class)->handle($task, User::factory()->create());

        $this->assertDatabaseMissing('activities', ['task_id' => $task->id, 'action' => 'approval_requested']);
    }
}
