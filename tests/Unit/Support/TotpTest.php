<?php

namespace Tests\Unit\Support;

use App\Support\Totp;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** Authenticator-app codes (RFC 6238, SHA-1, 6 digits, 30 seconds). */
class TotpTest extends TestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32 of the RFC test key "12345678901234567890"

    #[TestWith([59, '287082'])]
    #[TestWith([1111111109, '081804'])]
    #[TestWith([2000000000, '279037'])]
    public function test_codes_match_the_rfc_test_vectors(int $time, string $code): void
    {
        $this->assertSame($code, Totp::code(self::SECRET, Totp::currentStep($time)));
    }

    public function test_base32_round_trips_and_encodes_the_rfc_key(): void
    {
        $this->assertSame(self::SECRET, Totp::base32Encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::SECRET));
    }

    public function test_a_code_is_accepted_up_to_one_step_early_or_late(): void
    {
        $time = 1111111109;
        $previous = Totp::code(self::SECRET, Totp::currentStep($time) - 1);
        $next = Totp::code(self::SECRET, Totp::currentStep($time) + 1);
        $tooOld = Totp::code(self::SECRET, Totp::currentStep($time) - 2);

        $this->assertSame(Totp::currentStep($time) - 1, Totp::verify(self::SECRET, $previous, null, $time));
        $this->assertSame(Totp::currentStep($time) + 1, Totp::verify(self::SECRET, $next, null, $time));
        $this->assertNull(Totp::verify(self::SECRET, $tooOld, null, $time));
    }

    public function test_a_code_at_or_before_the_last_used_step_is_refused(): void
    {
        $time = 1111111109;
        $step = Totp::currentStep($time);

        $this->assertNull(Totp::verify(self::SECRET, '081804', $step, $time));
    }

    public function test_spaces_in_a_typed_code_are_ignored_and_wrong_lengths_refused(): void
    {
        $this->assertSame(Totp::currentStep(59), Totp::verify(self::SECRET, '287 082', null, 59));
        $this->assertNull(Totp::verify(self::SECRET, '28708', null, 59));
    }

    public function test_the_setup_link_names_the_account_and_issuer(): void
    {
        $uri = Totp::uri(self::SECRET, 'sam@example.com', 'TaskFlow');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.self::SECRET, $uri);
        $this->assertStringContainsString('issuer=TaskFlow', $uri);
        $this->assertStringContainsString('digits=6', $uri);
    }

    public function test_new_secrets_are_random_base32(): void
    {
        $secret = Totp::generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertNotSame($secret, Totp::generateSecret());
    }
}
