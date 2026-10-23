<?php

namespace Tests\Feature\Actions\Files;

use App\Actions\Files\StoreUploads;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** A batch of uploads is stored all or none, within the workspace's storage allowance. */
class StoreUploadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_that_does_not_fit_stores_no_file_at_all(): void
    {
        Storage::fake('local');
        config(['taskflow.workspace_storage_mb' => 1]);
        $project = Project::factory()->create();
        $files = [
            UploadedFile::fake()->create('a.pdf', 400, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 400, 'application/pdf'),
            UploadedFile::fake()->create('c.pdf', 400, 'application/pdf'),
        ];

        try {
            app(StoreUploads::class)->handle($files, $project, User::factory()->create());
            $this->fail('The batch should not fit.');
        } catch (ValidationException $e) {
            $this->assertSame([$project->workspace->storageFullMessage()], $e->errors()['upload']);
        }

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_batch_that_fits_stores_every_file(): void
    {
        Storage::fake('local');
        config(['taskflow.workspace_storage_mb' => 1]);
        $project = Project::factory()->create();
        $files = [
            UploadedFile::fake()->create('a.pdf', 300, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 300, 'application/pdf'),
        ];

        $stored = app(StoreUploads::class)->handle($files, $project, User::factory()->create());

        $this->assertSame(['a.pdf', 'b.pdf'], $stored->pluck('original_name')->all());
        $this->assertDatabaseCount('attachments', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_uploads_go_to_the_configured_disk_and_a_failed_batch_is_removed_from_it(): void
    {
        Storage::fake('s3');
        config(['taskflow.uploads_disk' => 's3']);
        $project = Project::factory()->create();
        $by = User::factory()->create();

        $stored = app(StoreUploads::class)->handle([UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], $project, $by);
        $this->assertSame('s3', $stored->sole()->disk);
        Storage::disk('s3')->assertExists($stored->sole()->path);

        try {
            app(StoreUploads::class)->handle([
                UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->createWithContent('c.pdf', '<?php system($_GET["c"]);'),
            ], $project, $by);
            $this->fail('The script should be refused.');
        } catch (ValidationException) {
        }

        $this->assertDatabaseCount('attachments', 1);
        $this->assertSame([$stored->sole()->path], Storage::disk('s3')->allFiles());
    }

    public function test_empty_batch_stores_nothing(): void
    {
        $stored = app(StoreUploads::class)->handle([], Project::factory()->create(), User::factory()->create());

        $this->assertTrue($stored->isEmpty());
    }
}
