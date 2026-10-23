<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Opening and downloading stored files. */
class AttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pdf_opens_in_the_browser_when_asked(): void
    {
        [$user, $attachment] = $this->file('application/pdf', 'brief.pdf');

        $response = $this->actingAs($user)->get(route('attachments.download', $attachment).'?inline=1');

        $response->assertOk();
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_other_files_always_download(): void
    {
        [$user, $attachment] = $this->file('text/html', 'page.html');

        $response = $this->actingAs($user)->get(route('attachments.download', $attachment).'?inline=1');

        $response->assertOk();
        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_a_file_missing_from_storage_answers_404(): void
    {
        [$user, $attachment] = $this->file('application/pdf', 'brief.pdf');
        Storage::disk('local')->delete($attachment->path);

        $this->actingAs($user)->get(route('attachments.download', $attachment))->assertNotFound();
    }

    public function test_someone_from_another_workspace_cannot_download_the_file(): void
    {
        [, $attachment] = $this->file('application/pdf', 'brief.pdf');
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create(), 'admin')->create();

        $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    /**
     * @return array{User, Attachment}
     */
    private function file(string $mime, string $name): array
    {
        Storage::fake('local');
        $workspace = Workspace::factory()->create();
        $attachment = Attachment::factory()->for(Project::factory()->for($workspace))->create(['mime_type' => $mime, 'original_name' => $name, 'path' => 'attachments/'.$name]);
        Storage::disk('local')->put($attachment->path, 'content');

        return [User::factory()->inWorkspace($workspace)->create(), $attachment];
    }
}
