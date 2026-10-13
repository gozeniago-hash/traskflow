<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    /** Image types shown in the in-app viewer. SVG is excluded on purpose: it can carry scripts. */
    public const VIEWABLE_IMAGES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif'];

    protected $fillable = ['project_id', 'task_id', 'comment_id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'client_visible'];

    protected function casts(): array
    {
        return ['client_visible' => 'boolean', 'size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public static function storeUpload(UploadedFile $file, Project $project, User $user, ?Task $task = null, bool $clientVisible = false, ?Comment $comment = null): self
    {
        $path = $file->store('attachments/'.$project->id, 'local');

        return static::create([
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'comment_id' => $comment?->id,
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(), // detected from content, not the browser's guess
            'size' => $file->getSize(),
            'client_visible' => $clientVisible,
        ]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVisibleToClient(): bool
    {
        if ($this->comment_id) {
            return $this->comment && ! $this->comment->is_internal && (bool) $this->task?->client_visible;
        }

        return $this->task_id ? (bool) $this->task?->client_visible : $this->client_visible;
    }

    public function isImage(): bool
    {
        if (in_array(strtolower((string) $this->mime_type), self::VIEWABLE_IMAGES, true)) {
            return true;
        }

        // Older uploads may have a generic type stored; fall back to the extension.
        return in_array(strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'avif'], true);
    }

    /** The Content-Type to send when displaying the file in the browser. */
    public function displayMimeType(): string
    {
        if (in_array(strtolower((string) $this->mime_type), self::VIEWABLE_IMAGES, true) || $this->isPdf()) {
            return strtolower($this->mime_type);
        }

        return match (strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'bmp' => 'image/bmp', 'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
    }

    public function isPdf(): bool
    {
        return strtolower((string) $this->mime_type) === 'application/pdf'
            || strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION)) === 'pdf' && str_contains((string) $this->mime_type, 'pdf');
    }

    public function viewUrl(): string
    {
        return route('attachments.download', [$this, 'inline' => 1]);
    }

    public function downloadUrl(): string
    {
        return route('attachments.download', $this);
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION) ?: 'FILE');
    }
}
