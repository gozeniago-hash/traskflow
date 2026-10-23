<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\VerifyTwoFactorCode;
use App\Models\User;
use App\Support\Totp;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The second sign-in step: authenticator codes and single-use recovery codes. */
class VerifyTwoFactorCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_the_current_authenticator_code_once(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $user = User::factory()->withTwoFactor()->create();
        $code = Totp::code(UserFactory::TWO_FACTOR_SECRET, Totp::currentStep());
        $verify = app(VerifyTwoFactorCode::class);

        $first = $verify->handle($user, $code);
        $replay = $verify->handle($user, $code);

        $this->assertTrue($first);
        $this->assertFalse($replay);
    }

    public function test_a_code_replayed_by_a_request_that_loaded_the_account_earlier_is_refused(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $user = User::factory()->withTwoFactor()->create();
        $sameAccountLoadedByASecondRequest = User::find($user->id);
        $code = Totp::code(UserFactory::TWO_FACTOR_SECRET, Totp::currentStep());
        app(VerifyTwoFactorCode::class)->handle($user, $code);

        $replay = app(VerifyTwoFactorCode::class)->handle($sameAccountLoadedByASecondRequest, $code);

        $this->assertFalse($replay);
    }

    public function test_two_recovery_codes_used_at_the_same_time_are_both_used_up(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $secondRequest = User::find($user->id);
        [$first, $second] = UserFactory::RECOVERY_CODES;

        app(VerifyTwoFactorCode::class)->handle($user, $first);
        app(VerifyTwoFactorCode::class)->handle($secondRequest, $second);

        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_a_recovery_code_works_once_ignoring_case_and_spaces(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $verify = app(VerifyTwoFactorCode::class);

        $first = $verify->handle($user, ' AAAAA-AAAAA ');
        $again = $verify->handle($user, 'aaaaa-aaaaa');

        $this->assertTrue($first);
        $this->assertFalse($again);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.recovery_code_used', 'user_id' => $user->id]);
    }

    public function test_refuses_any_code_when_two_step_verification_is_off(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(app(VerifyTwoFactorCode::class)->handle($user, '123456'));
    }
}
