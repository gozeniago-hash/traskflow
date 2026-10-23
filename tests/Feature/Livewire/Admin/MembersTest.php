<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Members;
use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Invites;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The members page: inviting people, changing roles and removing people. */
class MembersTest extends TestCase
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
    public function test_people_who_are_not_admins_cannot_open_members(string $role): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs(User::factory()->inWorkspace($workspace, $role)->create())->get(route('members'));

        $response->assertForbidden();
    }

    public function test_client_contacts_are_sent_to_their_portal(): void
    {
        $workspace = Workspace::factory()->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create();

        $response = $this->actingAs($contact)->get(route('members'));

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_inviting_several_addresses_at_once_skips_people_already_in_the_workspace(): void
    {
        Mail::fake();
        [$admin, $workspace] = $this->admin();
        $existing = User::factory()->inWorkspace($workspace)->create(['email' => 'leo@example.com']);

        Livewire::actingAs($admin)->test(Members::class)->call('openInvite')
            ->set('emails', "Sam@Example.com, ana@example.com\nleo@example.com")->set('role', 'manager')
            ->call('invite')->assertHasNoErrors()->assertSet('emails', '');

        $this->assertSame(['ana@example.com', 'sam@example.com'], Invitation::orderBy('email')->pluck('email')->all());
        $this->assertSame(['manager'], Invitation::distinct()->pluck('role')->all());
        Mail::assertSent(InvitationMail::class, 2);
        Mail::assertNotSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo($existing->email));
        $this->assertSame(2, AuditLog::where('action', 'invitation.sent')->count());
    }

    public function test_an_invalid_address_stops_the_whole_invite(): void
    {
        Mail::fake();
        [$admin] = $this->admin();

        $page = Livewire::actingAs($admin)->test(Members::class)->call('openInvite')
            ->set('emails', 'sam@example.com not-an-email')->call('invite');

        $this->assertSame(['Not a valid email: not-an-email'], $page->errors()->get('emails'));
        $this->assertSame(0, Invitation::count());
        Mail::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesThatCannotBeInvited(): array
    {
        return ['owner' => ['owner'], 'client' => ['client']];
    }

    #[DataProvider('rolesThatCannotBeInvited')]
    public function test_people_can_only_be_invited_as_admin_manager_or_member(string $role): void
    {
        Mail::fake();
        [$admin] = $this->admin();

        Livewire::actingAs($admin)->test(Members::class)->call('openInvite')
            ->set('emails', 'sam@example.com')->set('role', $role)->call('invite')
            ->assertHasErrors(['role' => 'in']);

        $this->assertSame(0, Invitation::count());
    }

    public function test_teams_of_another_workspace_cannot_be_given_to_an_invitation(): void
    {
        Mail::fake();
        [$admin] = $this->admin();
        $foreignTeam = Team::factory()->create();

        Livewire::actingAs($admin)->test(Members::class)->call('openInvite')
            ->set('emails', 'sam@example.com')->set('inviteTeams', [(string) $foreignTeam->id])->call('invite')
            ->assertHasErrors(['inviteTeams.0' => 'exists']);

        $this->assertSame(0, Invitation::count());
    }

    public function test_invitations_stop_when_the_hourly_allowance_is_used_up(): void
    {
        Mail::fake();
        [$admin, $workspace] = $this->admin();
        for ($i = 0; $i < Invites::PER_HOUR; $i++) {
            RateLimiter::hit('invites:'.$workspace->id, 3600);
        }

        $page = Livewire::actingAs($admin)->test(Members::class)->call('openInvite')
            ->set('emails', 'sam@example.com')->call('invite');

        $this->assertSame(['This workspace can send 50 invitations an hour. Try again later, or send fewer at once.'], $page->errors()->get('emails'));
        Mail::assertNothingSent();
    }

    public function test_changing_a_role_is_logged(): void
    {
        [$admin, $workspace] = $this->admin();
        $member = User::factory()->inWorkspace($workspace)->create();

        Livewire::actingAs($admin)->test(Members::class)->call('changeRole', $member->id, 'manager');

        $this->assertSame('manager', $member->fresh()->role());
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.role_changed', 'subject_id' => $member->id]);
    }

    public function test_the_owners_role_cannot_be_changed(): void
    {
        [$admin, $workspace] = $this->admin();
        $owner = User::factory()->inWorkspace($workspace, 'owner')->create();

        Livewire::actingAs($admin)->test(Members::class)->call('changeRole', $owner->id, 'member')
            ->assertDispatched('toast', fn (string $name, array $params) => $params['type'] === 'error' && $params['message'] === 'The owner role cannot be changed.');

        $this->assertSame('owner', $owner->fresh()->role());
        $this->assertSame(0, AuditLog::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRoles(): array
    {
        return ['owner' => ['owner'], 'client' => ['client'], 'made up' => ['superuser']];
    }

    #[DataProvider('invalidRoles')]
    public function test_a_role_outside_admin_manager_and_member_is_rejected_with_422(string $role): void
    {
        [$admin, $workspace] = $this->admin();
        $member = User::factory()->inWorkspace($workspace)->create();

        Livewire::actingAs($admin)->test(Members::class)->call('changeRole', $member->id, $role)->assertStatus(422);

        $this->assertSame('member', $member->fresh()->role());
    }

    public function test_removing_someone_takes_them_out_of_its_teams_and_projects(): void
    {
        [$admin, $workspace] = $this->admin();
        $team = Team::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->create();
        $otherWorkspacesProject = Project::factory()->create();
        $member = User::factory()->inWorkspace($workspace)->hasAttached($team)->create();
        $project->members()->attach($member->id);
        $otherWorkspacesProject->members()->attach($member->id);

        Livewire::actingAs($admin)->test(Members::class)->call('remove', $member->id);

        $member->refresh();
        $this->assertNull($member->current_workspace_id);
        $this->assertFalse($workspace->users()->whereKey($member->id)->exists());
        $this->assertSame(0, $member->teams()->count());
        $this->assertSame([$otherWorkspacesProject->id], $member->projects()->pluck('projects.id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.removed', 'subject_id' => $member->id]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function peopleWhoCannotBeRemoved(): array
    {
        return ['the owner' => ['owner'], 'yourself' => ['self']];
    }

    #[DataProvider('peopleWhoCannotBeRemoved')]
    public function test_the_owner_and_yourself_cannot_be_removed(string $who): void
    {
        [$admin, $workspace] = $this->admin();
        $target = $who === 'self' ? $admin : User::factory()->inWorkspace($workspace, 'owner')->create();

        Livewire::actingAs($admin)->test(Members::class)->call('remove', $target->id)
            ->assertDispatched('toast', fn (string $name, array $params) => $params['type'] === 'error');

        $this->assertTrue($workspace->users()->whereKey($target->id)->exists());
    }

    public function test_revoking_a_pending_invitation_deletes_it(): void
    {
        [$admin, $workspace] = $this->admin();
        $invitation = Invitation::factory()->for($workspace)->create();

        Livewire::actingAs($admin)->test(Members::class)->call('revoke', $invitation->id);

        $this->assertModelMissing($invitation);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.revoked']);
    }

    public function test_an_invitation_of_another_workspace_cannot_be_revoked(): void
    {
        [$admin] = $this->admin();
        $elsewhere = Invitation::factory()->create();

        Livewire::actingAs($admin)->test(Members::class)->call('revoke', $elsewhere->id);

        $this->assertModelExists($elsewhere);
        $this->assertSame(0, AuditLog::count());
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
