<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The invitation page and accepting it. */
class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_invitation_link_answers_404(): void
    {
        $this->get(route('invitations.show', 'no-such-token'))->assertNotFound();
    }

    public function test_the_invitation_page_names_the_workspace(): void
    {
        $this->withoutVite();
        $invitation = Invitation::factory()->for(Workspace::factory()->create(['name' => 'Brightside']))->create();

        $this->get(route('invitations.show', $invitation->token))->assertOk()->assertSee('Brightside');
    }

    public function test_a_new_person_must_give_a_name_and_confirm_their_password(): void
    {
        $invitation = Invitation::factory()->create();

        $response = $this->post(route('invitations.accept', $invitation->token), ['password' => 'a-long-passphrase', 'password_confirmation' => 'different']);

        $response->assertSessionHasErrors(['name', 'password']);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_a_new_person_joins_and_is_signed_in(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'sam@example.com']);

        $response = $this->post(route('invitations.accept', $invitation->token), ['name' => 'Sam Lee', 'password' => 'a-long-passphrase', 'password_confirmation' => 'a-long-passphrase']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'sam@example.com')->sole());
    }

    public function test_someone_with_an_account_must_give_their_current_password(): void
    {
        $person = User::factory()->create();
        $invitation = Invitation::factory()->create(['email' => $person->email]);

        $response = $this->post(route('invitations.accept', $invitation->token), ['password' => 'wrong']);

        $response->assertSessionHasErrors(['password' => 'That password is not correct.']);
        $this->assertGuest();
    }

    public function test_an_existing_member_keeps_their_role(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();
        $invitation = Invitation::factory()->for($workspace)->create(['email' => $admin->email]);

        $this->post(route('invitations.accept', $invitation->token), ['password' => 'password'])->assertRedirect();

        $this->assertSame('admin', $admin->fresh()->role());
    }

    public function test_a_used_invitation_answers_410(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $this->post(route('invitations.accept', $invitation->token), ['name' => 'Sam', 'password' => 'a-long-passphrase', 'password_confirmation' => 'a-long-passphrase'])->assertGone();
    }
}
