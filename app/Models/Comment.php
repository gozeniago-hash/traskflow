<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends BaseModel
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $fillable = ['task_id', 'user_id', 'body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->oldest();
    }

    /** Deletes the comment and its stored files. */
    public function deleteWithFiles(): void
    {
        foreach ($this->attachments as $attachment) {
            $attachment->delete();
        }
        $this->delete();
    }
}
