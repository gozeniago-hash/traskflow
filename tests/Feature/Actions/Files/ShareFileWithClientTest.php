<?php

namespace Tests\Feature\Actions\Files;

use App\Actions\Files\ShareFileWithClient;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sharing a project file with the client. */
class ShareFileWithClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_the_state_asked_for_even_when_asked_twice(): void
    {
        $file = Attachment::factory()->create();
        $share = app(ShareFileWithClient::class);

        $share->handle($file, true);
        $share->handle($file, true);

        $this->assertTrue($file->fresh()->client_visible);
    }
}
