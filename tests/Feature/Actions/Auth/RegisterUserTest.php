<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\RegisterUser;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Sign-up: a new account and the workspace it owns. */
class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_owner_their_workspace_and_a_general_team(): void
    {
        $user = app(RegisterUser::class)->handle(['name' => 'Sam Lee', 'email' => ' Sam@Example.COM ', 'password' => 'a-long-passphrase', 'workspace' => 'Acme']);

        $this->assertSame('sam@example.com', $user->email);
        $workspace = Workspace::sole();
        $this->assertSame('Acme', $workspace->name);
        $this->assertSame($workspace->id, $user->fresh()->current_workspace_id);
        $this->assertSame('owner', $user->fresh()->role());
        $this->assertSame(['General'], $user->teams()->pluck('name')->all());
    }

    public function test_an_email_taken_at_the_same_moment_gives_a_normal_error_and_no_workspace(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        try {
            app(RegisterUser::class)->handle(['name' => 'Sam Lee', 'email' => 'Sam@Example.com', 'password' => 'a-long-passphrase', 'workspace' => 'Acme']);
            $this->fail('A second account with the same email was created.');
        } catch (ValidationException $e) {
            $this->assertSame(['The email has already been taken.'], $e->errors()['email']);
        }

        $this->assertSame(0, Workspace::count());
        $this->assertSame(1, User::count());
    }
}
