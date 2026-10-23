<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/** Sign-up and sign-in with emails in any case. */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_signed_up_in_mixed_case_is_stored_and_signs_in_in_lowercase(): void
    {
        config(['taskflow.verify_email' => false]);
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->post('/register', $this->signUp('New@Example.COM'));
        auth()->logout();

        $response = $this->post('/login', ['email' => 'new@example.com', 'password' => 'a-long-passphrase-42']);

        $response->assertRedirect();
        $user = User::where('email', 'new@example.com')->sole();
        $this->assertAuthenticatedAs($user);
    }

    public function test_signing_up_again_with_the_email_in_another_case_is_refused(): void
    {
        config(['taskflow.verify_email' => false]);
        $this->withoutMiddleware(ThrottleRequests::class);
        User::factory()->create(['email' => 'new@example.com']);

        $response = $this->post('/register', $this->signUp('NEW@example.com'));

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    /**
     * @return array<string, string>
     */
    private function signUp(string $email): array
    {
        return ['name' => 'New Person', 'email' => $email, 'workspace' => 'Acme', 'password' => 'a-long-passphrase-42', 'password_confirmation' => 'a-long-passphrase-42'];
    }
}
