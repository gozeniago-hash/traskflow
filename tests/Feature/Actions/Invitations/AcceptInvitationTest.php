<?php

namespace Tests\Feature\Actions\Invitations;

use App\Actions\Invitations\AcceptInvitation;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Joining a workspace through an invitation, including double submits and revokes at the same moment. */
class AcceptInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_person_gets_an_account_the_invited_role_and_the_chosen_teams(): void
    {
        $workspace = Workspace::factory()->create();
        $design = Team::factory()->for($workspace)->create();
        Team::factory()->for($workspace)->create();
        $invitation = Invitation::factory()->for($workspace)->forRole('manager')->create(['email' => 'sam@example.com', 'team_ids' => [$design->id]]);

        $user = app(AcceptInvitation::class)->handle($invitation, null, ['name' => 'Sam Lee', 'password' => 'a-long-passphrase']);

        $this->assertSame('sam@example.com', $user->email);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('manager', $user->fresh()->role());
        $this->assertSame([$design->id], $user->teams()->pluck('teams.id')->all());
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_someone_already_in_the_workspace_keeps_their_role(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();
        $staleInvitation = Invitation::factory()->for($workspace)->forClient(Client::factory()->for($workspace)->create())->create(['email' => $admin->email]);

        app(AcceptInvitation::class)->handle($staleInvitation, $admin);

        $this->assertSame('admin', $admin->fresh()->role());
        $this->assertNull($admin->fresh()->clientId());
    }

    public function test_an_invitation_works_only_once(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'sam@example.com']);
        $accept = app(AcceptInvitation::class);
        $accept->handle($invitation, null, ['name' => 'Sam Lee', 'password' => 'a-long-passphrase']);

        $second = $accept->handle($invitation, null, ['name' => 'Sam Again', 'password' => 'a-long-passphrase']);

        $this->assertNull($second);
        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
    }

    public function test_an_invitation_revoked_while_it_is_being_accepted_lets_nobody_in(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'sam@example.com']);
        Invitation::whereKey($invitation->id)->delete();

        $user = app(AcceptInvitation::class)->handle($invitation, null, ['name' => 'Sam Lee', 'password' => 'a-long-passphrase']);

        $this->assertNull($user);
        $this->assertDatabaseMissing('users', ['email' => 'sam@example.com']);
    }

    public function test_an_expired_invitation_lets_nobody_in(): void
    {
        $workspace = Workspace::factory()->create();
        $person = User::factory()->create();
        $invitation = Invitation::factory()->for($workspace)->expired()->create(['email' => $person->email]);

        $user = app(AcceptInvitation::class)->handle($invitation, $person);

        $this->assertNull($user);
        $this->assertFalse($workspace->users()->whereKey($person->id)->exists());
    }
}
