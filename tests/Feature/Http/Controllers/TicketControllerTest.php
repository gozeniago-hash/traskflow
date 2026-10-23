<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Short ticket links such as /t/WEB-12. */
class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_is_taken_to_the_task_in_the_projects_default_view(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create(['key' => 'WEB', 'default_view' => 'board']);
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs(User::factory()->inWorkspace($workspace)->create())->get(route('tickets.show', 'web-'.$task->number));

        $response->assertRedirect(route('projects.show', [$project, 'board']).'?task='.$task->id);
    }

    public function test_a_client_is_taken_to_the_shared_task_in_the_portal(): void
    {
        $workspace = Workspace::factory()->create();
        $client = Client::factory()->for($workspace)->create();
        $project = Project::factory()->withPortal($client)->create(['key' => 'WEB']);
        $task = Task::factory()->for($project)->sharedWithClient()->create();

        $response = $this->actingAs(User::factory()->inWorkspace($workspace, 'client', $client)->create())->get(route('tickets.show', 'WEB-'.$task->number));

        $response->assertRedirect(route('portal.projects.show', ['project' => $project, 'tab' => 'tasks', 'task' => $task->id]));
    }

    public function test_a_client_gets_404_for_a_task_that_is_not_shared(): void
    {
        $workspace = Workspace::factory()->create();
        $client = Client::factory()->for($workspace)->create();
        $task = Task::factory()->for(Project::factory()->withPortal($client)->create(['key' => 'WEB']))->create();

        $this->actingAs(User::factory()->inWorkspace($workspace, 'client', $client)->create())
            ->get(route('tickets.show', 'WEB-'.$task->number))->assertNotFound();
    }

    public function test_an_unknown_or_malformed_ticket_answers_404(): void
    {
        $workspace = Workspace::factory()->create();
        Project::factory()->for($workspace)->create(['key' => 'WEB']);
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        $this->get(route('tickets.show', 'WEB-999'))->assertNotFound();
        $this->get(route('tickets.show', 'not-a-ticket'))->assertNotFound();
    }

    public function test_a_ticket_from_another_workspace_answers_404(): void
    {
        $task = Task::factory()->for(Project::factory()->create(['key' => 'WEB']))->create();
        $this->actingAs(User::factory()->inWorkspace(Workspace::factory()->create(), 'admin')->create());

        $this->get(route('tickets.show', 'WEB-'.$task->number))->assertNotFound();
    }
}
