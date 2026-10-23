<?php

namespace App\Actions\Files;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

/**
 * Moves every uploaded file onto another disk, for example from this server's drive to a bucket.
 * Each file is copied, checked, and its row switched over before the old copy is removed, so a
 * run that stops halfway loses nothing and can simply be run again. Files already on the target
 * are skipped; files missing from their disk are reported and left as they are.
 */
class MoveFilesToDisk
{
    /**
     * @return array{moved: int, missing: array<int, string>}
     */
    public function handle(string $to): array
    {
        $target = Storage::disk($to);
        $moved = 0;
        $missing = [];

        Attachment::query()->where('disk', '!=', $to)->chunkById(100, function ($attachments) use ($to, $target, &$moved, &$missing): void {
            foreach ($attachments as $attachment) {
                $source = Storage::disk($attachment->disk);
                $stream = $source->readStream($attachment->path);
                if (! is_resource($stream)) {
                    $missing[] = $attachment->disk.':'.$attachment->path;

                    continue;
                }

                try {
                    $target->writeStream($attachment->path, $stream);
                } finally {
                    fclose($stream);
                }
                if (! $target->exists($attachment->path)) {
                    $missing[] = $attachment->disk.':'.$attachment->path;

                    continue;
                }

                $from = $attachment->disk;
                $attachment->update(['disk' => $to]);
                Storage::disk($from)->delete($attachment->path);
                $moved++;
            }
        });

        return ['moved' => $moved, 'missing' => $missing];
    }
}
