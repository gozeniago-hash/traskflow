<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\ShareTaskWithClient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Showing a task to the client in the portal, or hiding it again. */
class ShareTaskWithClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_sharing_shows_the_task_to_the_client_and_records_it(): void
    {
        $task = Task::factory()->create();

        app(ShareTaskWithClient::class)->handle($task, User::factory()->create(), true);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'client_visible' => true]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'shared']);
    }

    public function test_unsharing_hides_the_task_and_records_it(): void
    {
        $task = Task::factory()->sharedWithClient()->create();

        app(ShareTaskWithClient::class)->handle($task, User::factory()->create(), false);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'client_visible' => false]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'unshared']);
    }

    public function test_sharing_an_already_shared_task_records_nothing(): void
    {
        $task = Task::factory()->sharedWithClient()->create();

        app(ShareTaskWithClient::class)->handle($task, User::factory()->create(), true);

        $this->assertDatabaseMissing('activities', ['task_id' => $task->id, 'action' => 'shared']);
    }
}
