<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** "Forgot password" by email. */
class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_asking_for_a_link_gives_the_same_answer_whether_or_not_the_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $message = 'If that email has an account, a reset link is on its way.';

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', $message);
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status', $message);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_valid_link_sets_the_new_password_and_is_logged(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase']);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('a-brand-new-passphrase', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'user_id' => $user->id]);
    }

    public function test_a_wrong_token_changes_nothing(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), ['token' => 'forged', 'email' => $user->email, 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase']);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
