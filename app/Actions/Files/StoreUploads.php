<?php

namespace App\Actions\Files;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores a batch of uploaded files, all or none. The whole batch must fit in the
 * workspace's storage allowance, and the workspace row stays locked while the files are
 * counted and saved, so two people uploading at once can't both squeeze past the limit.
 */
class StoreUploads
{
    /**
     * @param array<int, UploadedFile> $files
     * @return Collection<int, Attachment>
     *
     * @throws ValidationException when the batch doesn't fit
     */
    public function handle(array $files, Project $project, User $by, ?Task $task = null, bool $clientVisible = false, ?Comment $comment = null, ?ProjectDoc $doc = null): Collection
    {
        if ($files === []) {
            return collect();
        }

        $stored = [];
        try {
            return DB::transaction(function () use ($files, $project, $by, $task, $clientVisible, $comment, $doc, &$stored): Collection {
                $workspace = Workspace::whereKey($project->workspace_id)->lockForUpdate()->firstOrFail();
                $total = array_sum(array_map(fn (UploadedFile $file) => (int) $file->getSize(), $files));
                if (! $workspace->hasRoomFor($total)) {
                    throw ValidationException::withMessages(['upload' => $workspace->storageFullMessage()]);
                }

                return collect($files)->map(function (UploadedFile $file) use ($project, $by, $task, $clientVisible, $comment, $doc, &$stored): Attachment {
                    $attachment = Attachment::storeUpload($file, $project, $by, $task, $clientVisible, $comment, $doc);
                    $stored[] = ['disk' => $attachment->disk, 'path' => $attachment->path];

                    return $attachment;
                });
            });
        } catch (Throwable $e) {
            foreach ($stored as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }

            throw $e;
        }
    }
}
