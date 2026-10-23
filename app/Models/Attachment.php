<?php

namespace App\Models;

use App\Rules\SafeUpload;
use App\Rules\WithinStorageQuota;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Attachment extends BaseModel
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /** Image types shown in the in-app viewer. SVG is excluded on purpose: it can carry scripts. */
    public const VIEWABLE_IMAGES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif'];

    protected $fillable = ['project_id', 'task_id', 'comment_id', 'doc_id', 'user_id', 'disk', 'path', 'original_name', 'title', 'description', 'group', 'meta', 'mime_type', 'size', 'client_visible'];

    protected function casts(): array
    {
        return ['client_visible' => 'boolean', 'size' => 'integer', 'meta' => 'array'];
    }

    /** While true, deleting a row leaves its stored file in place (see App\Support\Undo). */
    public static bool $keepFiles = false;

    protected static function booted(): void
    {
        static::deleted(function (Attachment $attachment): void {
            if (! static::$keepFiles) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
        });
    }

    /** Validation for every upload: size, allowed type, and the workspace's storage allowance. */
    public static function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:'.config('taskflow.max_upload_kb'), new SafeUpload, new WithinStorageQuota];
    }

    /**
     * Validation for the details people add to a file: title, description, group and extra label/value rows.
     *
     * @return array<string, array<int, string>>
     */
    public static function detailRules(string $prefix = ''): array
    {
        return [
            $prefix.'title' => ['nullable', 'string', 'max:150'],
            $prefix.'description' => ['nullable', 'string', 'max:1000'],
            $prefix.'group' => ['nullable', 'string', 'max:60'],
            $prefix.'meta' => ['array', 'max:20'],
            $prefix.'meta.*.label' => ['nullable', 'string', 'max:60'],
            $prefix.'meta.*.value' => ['nullable', 'string', 'max:500'],
        ];
    }

    public static function storeUpload(UploadedFile $file, Project $project, User $user, ?Task $task = null, bool $clientVisible = false, ?Comment $comment = null, ?ProjectDoc $doc = null): self
    {
        // Last line of defence: the same checks as rules(), in case a caller skipped validation.
        $validator = Validator::make(['file' => $file], ['file' => ['file', new SafeUpload, new WithinStorageQuota($project->workspace_id)]], [], ['file' => 'file']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['upload' => $validator->errors()->first('file')]);
        }

        $disk = (string) config('taskflow.uploads_disk');
        $path = $file->store('attachments/'.$project->id, $disk);

        return static::create([
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'comment_id' => $comment?->id,
            'doc_id' => $doc?->id,
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(), // detected from content, not the browser's guess
            'size' => $file->getSize(),
            'client_visible' => $clientVisible,
        ]);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<ProjectDoc, $this> */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(ProjectDoc::class, 'doc_id');
    }

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The name people see: the title they gave the file, or the name it was uploaded with. */
    public function displayName(): string
    {
        return $this->title ?: $this->original_name;
    }

    public function isVisibleToClient(): bool
    {
        if ($this->doc_id) {
            return (bool) $this->doc?->client_visible;
        }
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
