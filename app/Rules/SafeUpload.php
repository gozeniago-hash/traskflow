<?php

namespace App\Rules;

use Closure;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Only file types on the allow-list, and nothing whose content is a program or script,
 * whatever its name says.
 */
class SafeUpload implements ValidationRule
{
    /** Detected content types that are never accepted, even under an allowed extension. */
    public const BLOCKED_MIMES = [
        'application/x-dosexec', 'application/x-msdownload', 'application/x-msdos-program', 'application/vnd.microsoft.portable-executable',
        'application/x-executable', 'application/x-elf', 'application/x-sharedlib', 'application/x-pie-executable',
        'application/x-mach-binary', 'application/x-mach-o-executable', 'application/java-archive', 'application/x-java-archive',
        'application/vnd.android.package-archive', 'application/x-msi', 'application/x-ms-installer',
        'application/x-sh', 'text/x-shellscript', 'application/x-shellscript', 'text/x-php', 'application/x-php', 'application/x-httpd-php',
        'text/x-python', 'text/x-perl', 'application/x-perl', 'text/x-msdos-batch', 'application/x-bat',
        'text/html', 'application/xhtml+xml', 'text/javascript', 'application/javascript', 'application/x-javascript',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = strtolower(pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension === '' || ! in_array($extension, self::allowedExtensions(), true)) {
            $fail(($extension ? '.'.$extension.' files' : 'Files without an extension').' can’t be uploaded. Allowed: documents, images, design files, audio/video and archives.');

            return;
        }

        // Look at the content itself as well as the reported type: a script renamed to .pdf is still a script.
        $detected = [strtolower((string) $value->getMimeType())];
        $detected[] = self::sniff($value);
        $blocked = self::BLOCKED_MIMES;
        if (in_array($extension, ['txt', 'md', 'csv'], true)) {
            // Plain-text notes may legitimately contain markup; they're always downloaded, never opened in the browser.
            $blocked = array_diff($blocked, ['text/html', 'application/xhtml+xml', 'text/javascript', 'application/javascript', 'application/x-javascript']);
        }
        if (array_intersect($detected, $blocked)) {
            $fail('This file looks like a program or script, so it can’t be uploaded.');
        }
    }

    /**
     * The content type read from the file's first bytes, wherever it is kept: on this server, or in a
     * bucket when Livewire keeps temporary uploads there (then there is no local path to read).
     */
    private static function sniff(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path && is_readable($path)) {
            return strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->file($path));
        }
        if (! $file instanceof TemporaryUploadedFile) {
            return '';
        }

        $stream = $file->readStream();
        if (! is_resource($stream)) {
            return '';
        }
        try {
            $head = stream_get_contents($stream, 64 * 1024);
        } finally {
            fclose($stream);
        }

        return $head ? strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($head)) : '';
    }

    public static function allowedExtensions(): array
    {
        return config('taskflow.allowed_upload_extensions', []);
    }

    /** For the file pickers' accept attribute. */
    public static function accept(): string
    {
        return collect(self::allowedExtensions())->map(fn ($e) => '.'.$e)->implode(',');
    }
}
