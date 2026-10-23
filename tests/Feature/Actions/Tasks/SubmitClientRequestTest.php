<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\SubmitClientRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A request a client sends from the portal. */
class SubmitClientRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_becomes_a_shared_task_in_a_client_requests_section_at_the_top(): void
    {
        [$client, $project] = $this->portal();
        Section::factory()->for($project)->create(['name' => 'To do', 'position' => 1]);

        $task = app(SubmitClientRequest::class)->handle($project, $client, ['title' => 'New landing page', 'description' => 'For the spring sale', 'due_date' => null]);

        $this->assertSame('Client requests', $task->section->name);
        $this->assertSame(0, $task->section->position);
        $this->assertTrue($task->client_visible);
        $this->assertTrue($task->requested_by_client);
        $this->assertSame($client->id, $task->creator_id);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'client_request']);
    }

    public function test_two_requests_share_one_client_requests_section(): void
    {
        [$client, $project] = $this->portal();
        $submit = app(SubmitClientRequest::class);

        $first = $submit->handle($project, $client, ['title' => 'First']);
        $second = $submit->handle($project, $client, ['title' => 'Second']);

        $this->assertSame($first->section_id, $second->section_id);
        $this->assertSame(1, $project->sections()->where('name', 'Client requests')->count());
        $this->assertSame([1, 2], [$first->position, $second->position]);
    }

    /**
     * @return array{User, Project}
     */
    private function portal(): array
    {
        $workspace = Workspace::factory()->create();
        $customer = Client::factory()->for($workspace)->create();

        return [User::factory()->inWorkspace($workspace, 'client', $customer)->create(), Project::factory()->withPortal($customer)->create()];
    }
}
