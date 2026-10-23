<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Clients;
use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The clients page: client details, portal invitations and portal access. */
class ClientsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function refusedRoles(): array
    {
        return ['manager' => ['manager'], 'member' => ['member']];
    }

    #[DataProvider('refusedRoles')]
    public function test_people_who_are_not_admins_cannot_open_clients(string $role): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs(User::factory()->inWorkspace($workspace, $role)->create())->get(route('clients'));

        $response->assertForbidden();
    }

    public function test_client_contacts_are_sent_to_their_portal(): void
    {
        $workspace = Workspace::factory()->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create();

        $response = $this->actingAs($contact)->get(route('clients'));

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_adding_a_client_saves_its_details_and_logs_it(): void
    {
        [$admin, $workspace] = $this->admin();

        Livewire::actingAs($admin)->test(Clients::class)->call('create')
            ->set('form.name', 'Brightside')->set('form.email', 'hello@brightside.test')->call('save')
            ->assertHasNoErrors()->assertSet('showForm', false);

        $client = Client::sole();
        $this->assertSame([$workspace->id, 'Brightside', 'hello@brightside.test'], [$client->workspace_id, $client->name, $client->email]);
        $this->assertSame(['client.created'], AuditLog::pluck('action')->all());
    }

    public function test_a_client_needs_a_name_and_a_valid_email(): void
    {
        [$admin] = $this->admin();
        $page = Livewire::actingAs($admin)->test(Clients::class)->call('create');

        $page->set('form.name', '')->call('save')->assertHasErrors(['form.name' => 'required']);
        $page->set('form.name', str_repeat('a', 151))->call('save')->assertHasErrors(['form.name' => 'max']);
        $page->set('form.name', 'Brightside')->set('form.email', 'not-an-email')->call('save')->assertHasErrors(['form.email' => 'email']);

        $this->assertSame(0, Client::count());
    }

    public function test_editing_a_client_saves_and_logs_it(): void
    {
        [$admin, $workspace] = $this->admin();
        $client = Client::factory()->for($workspace)->create();

        Livewire::actingAs($admin)->test(Clients::class)->call('edit', $client->id)->set('form.name', 'Brightside Ltd')->call('save');

        $this->assertSame('Brightside Ltd', $client->fresh()->name);
        $this->assertSame(['client.updated'], AuditLog::pluck('action')->all());
    }

    public function test_the_client_being_edited_cannot_be_switched_from_the_browser(): void
    {
        [$admin] = $this->admin();
        $elsewhere = Client::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($admin)->test(Clients::class)->set('editingId', $elsewhere->id);
    }

    public function test_the_client_being_invited_to_cannot_be_switched_from_the_browser(): void
    {
        [$admin] = $this->admin();
        $elsewhere = Client::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($admin)->test(Clients::class)->set('inviteFor', $elsewhere->id);
    }

    public function test_a_portal_invitation_is_sent_once_and_the_form_clears(): void
    {
        Mail::fake();
        [$admin, $workspace] = $this->admin();
        $client = Client::factory()->for($workspace)->create();
        $page = Livewire::actingAs($admin)->test(Clients::class)->call('openInvite', $client->id)->set('inviteEmail', 'Pat@Brightside.test');

        $page->call('sendInvite')->assertHasNoErrors()->assertSet('inviteEmail', '');
        $page->call('sendInvite')->assertHasErrors(['inviteEmail' => 'required']);

        $invitation = Invitation::sole();
        $this->assertSame(['pat@brightside.test', 'client', $client->id], [$invitation->email, $invitation->role, $invitation->client_id]);
        Mail::assertSent(InvitationMail::class, 1);
        $page->assertSet('lastInviteUrl', $invitation->url());
    }

    public function test_someone_already_in_the_workspace_is_not_invited_to_the_portal(): void
    {
        Mail::fake();
        [$admin, $workspace] = $this->admin();
        $client = Client::factory()->for($workspace)->create();
        $member = User::factory()->inWorkspace($workspace)->create(['email' => 'leo@example.com']);

        $page = Livewire::actingAs($admin)->test(Clients::class)->call('openInvite', $client->id)
            ->set('inviteEmail', 'LEO@example.com')->call('sendInvite');

        $this->assertSame(['That person is already in this workspace.'], $page->errors()->get('inviteEmail'));
        $this->assertSame('member', $member->fresh()->role());
        Mail::assertNothingSent();
    }

    public function test_revoking_a_portal_invitation_deletes_it(): void
    {
        [$admin, $workspace] = $this->admin();
        $invitation = Invitation::factory()->forClient(Client::factory()->for($workspace)->create())->create();

        Livewire::actingAs($admin)->test(Clients::class)->call('revokeInvite', $invitation->id);

        $this->assertModelMissing($invitation);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.revoked']);
    }

    public function test_removing_a_contact_takes_away_their_portal_access(): void
    {
        [$admin, $workspace] = $this->admin();
        $client = Client::factory()->for($workspace)->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', $client)->create();
        $project = Project::factory()->withPortal($client)->create();

        Livewire::actingAs($admin)->test(Clients::class)->call('removeContact', $client->id, $contact->id);

        $this->assertFalse($workspace->users()->whereKey($contact->id)->exists());
        $this->actingAs($contact->fresh())->get(route('portal.projects.show', $project))->assertRedirect(route('login'));
    }

    public function test_deleting_a_client_keeps_its_projects(): void
    {
        [$admin, $workspace] = $this->admin();
        $client = Client::factory()->for($workspace)->create();
        $project = Project::factory()->withPortal($client)->create();

        Livewire::actingAs($admin)->test(Clients::class)->call('delete', $client->id);

        $this->assertModelMissing($client);
        $this->assertModelExists($project);
    }

    public function test_a_client_of_another_workspace_cannot_be_deleted(): void
    {
        [$admin] = $this->admin();
        $elsewhere = Client::factory()->create();

        Livewire::actingAs($admin)->test(Clients::class)->call('delete', $elsewhere->id)->assertNotFound();

        $this->assertModelExists($elsewhere);
    }

    /**
     * @return array{User, Workspace}
     */
    private function admin(): array
    {
        $workspace = Workspace::factory()->create();

        return [User::factory()->inWorkspace($workspace, 'admin')->create(), $workspace];
    }
}
