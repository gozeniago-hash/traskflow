<?php

namespace Tests\Feature\Actions\Files;

use App\Actions\Files\UpdateFileDetails;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Saving a file's title, description, group and extra details. */
class UpdateFileDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_the_details_trimmed_and_drops_extra_rows_without_a_label(): void
    {
        $file = Attachment::factory()->create(['original_name' => 'scan.pdf']);

        app(UpdateFileDetails::class)->handle($file, ' Signed contract ', 'Final version', ' Contracts ', [
            ['label' => ' Approved by ', 'value' => ' Ana '],
            ['label' => '  ', 'value' => 'orphan value'],
        ]);

        $file->refresh();
        $this->assertSame('Signed contract', $file->title);
        $this->assertSame('Final version', $file->description);
        $this->assertSame('Contracts', $file->group);
        $this->assertSame([['label' => 'Approved by', 'value' => 'Ana']], $file->meta);
        $this->assertSame('Signed contract', $file->displayName());
    }

    public function test_blank_values_clear_the_details_and_the_file_name_shows_again(): void
    {
        $file = Attachment::factory()->withDetails()->create(['original_name' => 'scan.pdf']);

        app(UpdateFileDetails::class)->handle($file, ' ', '', null, []);

        $file->refresh();
        $this->assertNull($file->title);
        $this->assertNull($file->description);
        $this->assertNull($file->group);
        $this->assertNull($file->meta);
        $this->assertSame('scan.pdf', $file->displayName());
    }
}
