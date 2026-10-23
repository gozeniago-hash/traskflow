<?php

namespace Tests\Unit\Support;

use App\Support\GitHub;
use PHPUnit\Framework\TestCase;

/** Finding ticket numbers in pull requests, and checking webhook signatures. */
class GitHubTest extends TestCase
{
    public function test_ticket_references_are_grouped_by_project_key(): void
    {
        $refs = GitHub::ticketRefs("Fixes web-5 and WEB-12\nbranch: app-3-login, again WEB-5");

        $this->assertSame(['WEB' => [5, 12], 'APP' => [3]], $refs);
    }

    public function test_keys_need_two_characters_starting_with_a_letter_and_numbers_at_most_seven_digits(): void
    {
        $this->assertSame([], GitHub::ticketRefs('x-1 9A-1 WEB-12345678'));
    }

    public function test_a_signature_must_match_the_secret_and_payload(): void
    {
        $payload = '{"action":"opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'secret');

        $this->assertTrue(GitHub::verifySignature($payload, $signature, 'secret'));
        $this->assertFalse(GitHub::verifySignature($payload, $signature, 'other-secret'));
        $this->assertFalse(GitHub::verifySignature($payload.' ', $signature, 'secret'));
        $this->assertFalse(GitHub::verifySignature($payload, substr($signature, 7), 'secret'));
        $this->assertFalse(GitHub::verifySignature($payload, null, 'secret'));
    }
}
