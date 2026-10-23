<?php

namespace Tests\Feature\Actions\Docs;

use App\Actions\Docs\ShareDocWithClient;
use App\Models\Activity;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sharing a doc with the client. */
class ShareDocWithClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_sharing_shows_up_in_notifications_once(): void
    {
        $this->actingAs(User::factory()->create());
        $doc = ProjectDoc::factory()->create();

        app(ShareDocWithClient::class)->handle($doc, true);
        app(ShareDocWithClient::class)->handle($doc, true);

        $this->assertTrue($doc->fresh()->client_visible);
        $this->assertSame(['shared'], Activity::where('kind', 'doc')->where('subject_id', $doc->id)->whereIn('action', ['shared', 'unshared'])->pluck('action')->all());
    }
}
