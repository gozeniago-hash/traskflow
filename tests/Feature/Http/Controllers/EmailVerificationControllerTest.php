<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Sending the email confirmation link again. */
class EmailVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_a_new_link_and_allows_three_a_minute(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        foreach (range(1, 3) as $attempt) {
            $this->post(route('verification.send'))->assertSessionHas('status', 'A new confirmation link is on its way.');
        }
        $fourth = $this->post(route('verification.send'));

        $fourth->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_someone_already_confirmed_is_sent_on_without_an_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect(route('dashboard'));

        Notification::assertNothingSent();
    }
}
