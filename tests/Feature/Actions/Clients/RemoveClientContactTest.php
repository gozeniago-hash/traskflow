<?php

namespace Tests\Feature\Actions\Clients;

use App\Actions\Clients\RemoveClientContact;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Taking away a client contact's portal access. */
class RemoveClientContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_contact_loses_access_to_the_workspace_at_once(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());
        $client = Client::factory()->for($workspace)->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', $client)->create();
        $project = Project::factory()->withPortal($client)->create();

        app(RemoveClientContact::class)->handle($client, $contact->id);

        $contact->refresh();
        $this->assertNull($contact->current_workspace_id);
        $this->assertFalse($contact->can('view', $project));
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.contact_removed', 'subject_id' => $client->id]);
    }

    public function test_nothing_is_logged_when_the_person_was_not_a_contact_of_that_client(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());
        $client = Client::factory()->for($workspace)->create();
        $member = User::factory()->inWorkspace($workspace)->create();

        app(RemoveClientContact::class)->handle($client, $member->id);

        $this->assertSame('member', $member->fresh()->role());
        $this->assertSame(0, AuditLog::where('action', 'client.contact_removed')->count());
    }
}
