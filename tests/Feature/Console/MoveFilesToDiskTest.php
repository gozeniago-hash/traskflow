<?php

namespace Tests\Feature\Console;

use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The files:move-to command people run on moving day. */
class MoveFilesToDiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_the_files_and_says_how_many(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Attachment::factory()->create(['disk' => 'local', 'path' => 'attachments/1/a.pdf']);
        Storage::disk('local')->put('attachments/1/a.pdf', 'a');

        $this->artisan('files:move-to', ['disk' => 's3'])
            ->expectsOutput('Moved 1 file to s3.')
            ->assertSuccessful();
    }

    public function test_it_fails_and_lists_files_it_could_not_find(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Attachment::factory()->create(['disk' => 'local', 'path' => 'attachments/1/gone.pdf']);

        $this->artisan('files:move-to', ['disk' => 's3'])
            ->expectsOutputToContain('local:attachments/1/gone.pdf')
            ->assertFailed();
    }

    public function test_it_refuses_a_disk_that_is_not_configured(): void
    {
        $this->artisan('files:move-to', ['disk' => 'nowhere'])
            ->expectsOutput('There is no "nowhere" disk in config/filesystems.php.')
            ->assertFailed();

        $this->assertDatabaseCount('attachments', 0);
    }
}
