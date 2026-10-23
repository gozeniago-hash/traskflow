<?php

namespace Tests\Feature\Actions\Clients;

use App\Actions\Clients\DeleteClient;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Deleting a client. */
class DeleteClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_lose_access_and_projects_are_kept(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());
        $client = Client::factory()->for($workspace)->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', $client)->create();
        $project = Project::factory()->withPortal($client)->create();

        app(DeleteClient::class)->handle($client);

        $this->assertModelMissing($client);
        $this->assertModelExists($project);
        $this->assertNull($project->fresh()->client_id);
        $this->assertNull($contact->fresh()->current_workspace_id);
        $this->assertFalse($workspace->users()->whereKey($contact->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.deleted']);
    }
}
