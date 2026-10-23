<?php

namespace App\Actions\Comments;

use App\Actions\Files\StoreUploads;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Posts a comment on a task with its files, all or nothing. Internal comments are hidden from the client. */
class AddComment
{
    public function __construct(protected StoreUploads $storeUploads) {}

    /**
     * @param array<int, UploadedFile> $files
     */
    public function handle(Task $task, User $by, string $body, bool $internal, array $files = []): Comment
    {
        return DB::transaction(function () use ($task, $by, $body, $internal, $files): Comment {
            $comment = $task->comments()->create(['user_id' => $by->id, 'body' => trim($body), 'is_internal' => $internal]);
            $this->storeUploads->handle($files, $task->project, $by, $task, false, $comment);

            return $comment;
        });
    }
}
