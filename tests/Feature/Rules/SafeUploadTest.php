<?php

namespace Tests\Feature\Rules;

use App\Rules\SafeUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** The content check still looks inside a file when Livewire keeps temporary uploads in a bucket. */
class SafeUploadTest extends TestCase
{
    #[TestWith(["<?php system(\$_GET['c']);", false])]
    #[TestWith(["%PDF-1.4\n%âãÏÓ\n1 0 obj << /Type /Catalog >> endobj\n", true])]
    public function test_a_temporary_upload_in_a_bucket_is_judged_by_its_content(string $content, bool $accepted): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('livewire-tmp/abc123.pdf', $content);
        $file = $this->bucketUpload('abc123.pdf', 'contract.pdf');

        $validator = Validator::make(['file' => $file], ['file' => [new SafeUpload]]);

        $this->assertSame($accepted, $validator->passes());
        if (! $accepted) {
            $this->assertSame('This file looks like a program or script, so it can’t be uploaded.', $validator->errors()->first('file'));
        }
    }

    /**
     * A Livewire temporary upload as it is in a real bucket: no path on this server, and a type
     * taken from what the browser claimed rather than the content.
     */
    private function bucketUpload(string $path, string $originalName): TemporaryUploadedFile
    {
        return new class($path, 's3', $originalName) extends TemporaryUploadedFile
        {
            public function __construct(string $path, string $disk, private string $name)
            {
                parent::__construct($path, $disk);
            }

            public function getRealPath(): string
            {
                return '';
            }

            public function getMimeType(): string
            {
                return 'application/pdf';
            }

            public function getClientOriginalName(): string
            {
                return $this->name;
            }
        };
    }
}
