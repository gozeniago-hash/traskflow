<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\CancelApproval;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Withdrawing an approval request. */
class CancelApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_clears_the_approval_request(): void
    {
        $task = Task::factory()->awaitingApproval()->create();

        app(CancelApproval::class)->handle($task);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'approval_status' => null, 'approval_by' => null, 'approval_at' => null]);
    }
}
