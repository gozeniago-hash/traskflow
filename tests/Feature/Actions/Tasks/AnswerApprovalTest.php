<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\AnswerApproval;
use App\Enums\ApprovalStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The client's answer to an approval request. */
class AnswerApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_records_who_approved_and_their_feedback(): void
    {
        [$client, $task] = $this->pendingApproval();

        $answered = app(AnswerApproval::class)->handle($task, $client, ApprovalStatus::Approved, ' Looks great ');

        $this->assertTrue($answered);
        $task->refresh();
        $this->assertSame(ApprovalStatus::Approved, $task->approval_status);
        $this->assertSame($client->id, $task->approval_by);
        $this->assertSame(['Looks great'], Comment::where('task_id', $task->id)->where('is_internal', false)->pluck('body')->all());
        $this->assertSame(['approved'], Activity::where('task_id', $task->id)->pluck('action')->all());
    }

    public function test_a_second_answer_is_ignored_once_the_first_is_in(): void
    {
        [$client, $task] = $this->pendingApproval();
        $colleague = User::factory()->inWorkspace($task->project->workspace, 'client', $task->project->client)->create();
        app(AnswerApproval::class)->handle($task, $client, ApprovalStatus::Approved);

        $second = app(AnswerApproval::class)->handle($task, $colleague, ApprovalStatus::ChangesRequested, 'Please change the colour');

        $this->assertFalse($second);
        $this->assertSame(ApprovalStatus::Approved, $task->fresh()->approval_status);
        $this->assertSame(1, Activity::where('task_id', $task->id)->count());
        $this->assertSame(0, Comment::count());
    }

    /**
     * @return array{User, Task}
     */
    private function pendingApproval(): array
    {
        $workspace = Workspace::factory()->create();
        $customer = Client::factory()->for($workspace)->create();
        $client = User::factory()->inWorkspace($workspace, 'client', $customer)->create();

        return [$client, Task::factory()->for(Project::factory()->withPortal($customer))->awaitingApproval()->create()];
    }
}
