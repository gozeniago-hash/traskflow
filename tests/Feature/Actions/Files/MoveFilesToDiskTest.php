<?php

namespace Tests\Feature\Actions\Files;

use App\Actions\Files\MoveFilesToDisk;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Moving uploaded files from this server's drive to a bucket. */
class MoveFilesToDiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('s3');
    }

    public function test_each_file_is_copied_switched_over_and_removed_from_the_old_disk(): void
    {
        $file = Attachment::factory()->create(['disk' => 'local', 'path' => 'attachments/1/brief.pdf']);
        Storage::disk('local')->put('attachments/1/brief.pdf', 'brief contents');

        $result = app(MoveFilesToDisk::class)->handle('s3');

        $this->assertSame(['moved' => 1, 'missing' => []], $result);
        $this->assertSame('s3', $file->fresh()->disk);
        $this->assertSame('brief contents', Storage::disk('s3')->get('attachments/1/brief.pdf'));
        Storage::disk('local')->assertMissing('attachments/1/brief.pdf');
    }

    public function test_a_file_missing_from_its_disk_is_reported_and_its_row_left_alone(): void
    {
        $file = Attachment::factory()->create(['disk' => 'local', 'path' => 'attachments/1/gone.pdf']);

        $result = app(MoveFilesToDisk::class)->handle('s3');

        $this->assertSame(['moved' => 0, 'missing' => ['local:attachments/1/gone.pdf']], $result);
        $this->assertSame('local', $file->fresh()->disk);
    }

    public function test_running_it_again_leaves_files_already_moved_untouched(): void
    {
        $file = Attachment::factory()->create(['disk' => 'local', 'path' => 'attachments/1/brief.pdf']);
        Storage::disk('local')->put('attachments/1/brief.pdf', 'brief contents');
        app(MoveFilesToDisk::class)->handle('s3');

        $result = app(MoveFilesToDisk::class)->handle('s3');

        $this->assertSame(['moved' => 0, 'missing' => []], $result);
        $this->assertSame('s3', $file->fresh()->disk);
        $this->assertSame('brief contents', Storage::disk('s3')->get('attachments/1/brief.pdf'));
    }
}
