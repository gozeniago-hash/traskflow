<?php

namespace Tests\Feature\Actions\Invitations;

use App\Actions\Invitations\SendInvitation;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Sending an invitation email. */
class SendInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_the_invitation_to_the_address_in_lowercase(): void
    {
        Mail::fake();
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();

        $invitation = app(SendInvitation::class)->handle($workspace, $admin, ' Sam@Example.com ', 'member');

        $this->assertSame('sam@example.com', $invitation->email);
        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('sam@example.com'));
    }

    public function test_sending_again_replaces_the_pending_invitation_so_one_address_has_one_role(): void
    {
        Mail::fake();
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();
        $first = app(SendInvitation::class)->handle($workspace, $admin, 'sam@example.com', 'member');

        $second = app(SendInvitation::class)->handle($workspace, $admin, 'sam@example.com', 'manager');

        $this->assertModelMissing($first);
        $this->assertSame(['manager'], Invitation::where('email', 'sam@example.com')->pluck('role')->all());
        $this->assertSame($second->id, Invitation::sole()->id);
    }

    public function test_an_accepted_invitation_is_kept_when_inviting_the_address_again(): void
    {
        Mail::fake();
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();
        $accepted = Invitation::factory()->for($workspace)->accepted()->create(['email' => 'sam@example.com']);

        app(SendInvitation::class)->handle($workspace, $admin, 'sam@example.com', 'member');

        $this->assertModelExists($accepted);
    }
}
