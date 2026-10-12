<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = ['project_id', 'task_id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'client_visible'];

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

    public static function storeUpload(UploadedFile $file, Project $project, User $user, ?Task $task = null, bool $clientVisible = false): self
    {
        $path = $file->store('attachments/'.$project->id, 'local');

        return static::create([
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVisibleToClient(): bool
    {
        return $this->task_id ? (bool) $this->task?->client_visible : $this->client_visible;
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
