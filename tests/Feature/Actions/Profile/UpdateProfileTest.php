<?php

namespace Tests\Feature\Actions\Profile;

use App\Actions\Profile\UpdateProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Saving a person's profile. */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_a_new_email_in_lowercase_and_logs_the_change(): void
    {
        config(['taskflow.verify_email' => false]);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->actingAs($user);

        $needsConfirming = app(UpdateProfile::class)->handle($user, $this->details('New@Example.COM'));

        $this->assertFalse($needsConfirming);
        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_changed', 'user_id' => $user->id]);
    }

    public function test_a_new_email_must_be_confirmed_when_the_app_sends_email(): void
    {
        Notification::fake();
        config(['taskflow.verify_email' => true]);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->actingAs($user);

        $needsConfirming = app(UpdateProfile::class)->handle($user, $this->details('new@example.com'));

        $this->assertTrue($needsConfirming);
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_keeping_the_same_email_in_another_case_is_no_change(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $this->actingAs($user);

        $needsConfirming = app(UpdateProfile::class)->handle($user, $this->details('SAM@example.com'));

        $this->assertFalse($needsConfirming);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.email_changed']);
    }

    public function test_an_email_someone_else_took_meanwhile_gives_a_normal_error(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        try {
            app(UpdateProfile::class)->handle($user, $this->details('taken@example.com'));
            $this->fail('Two accounts now share an email.');
        } catch (ValidationException $e) {
            $this->assertSame(['The email has already been taken.'], $e->errors()['email']);
        }

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    /**
     * @return array{name: string, email: string, title: string|null, avatar_color: string}
     */
    private function details(string $email): array
    {
        return ['name' => 'Sam Lee', 'email' => $email, 'title' => null, 'avatar_color' => User::AVATAR_COLORS[0]];
    }
}
