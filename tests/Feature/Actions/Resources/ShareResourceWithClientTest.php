<?php

namespace Tests\Feature\Actions\Resources;

use App\Actions\Resources\ShareResourceWithClient;
use App\Models\Attachment;
use App\Models\ProjectResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sharing a key resource with the client. */
class ShareResourceWithClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_file_resource_takes_its_file_along(): void
    {
        $file = Attachment::factory()->sharedWithClient()->create();
        $resource = ProjectResource::factory()->sharedWithClient()->create(['project_id' => $file->project_id, 'attachment_id' => $file->id, 'url' => null]);

        app(ShareResourceWithClient::class)->handle($resource, false);

        $this->assertFalse($resource->fresh()->client_visible);
        $this->assertFalse($file->fresh()->client_visible);
    }
}
