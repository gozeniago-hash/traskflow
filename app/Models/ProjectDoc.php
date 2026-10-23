<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDoc extends Model
{
    protected $fillable = ['project_id', 'title', 'content', 'client_visible', 'position', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['client_visible' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Content is always stored sanitized, no matter where it comes from.
        static::saving(function (ProjectDoc $doc): void {
            if ($doc->isDirty('content')) {
                $doc->content = HtmlSanitizer::clean($doc->content);
            }
        });

        static::deleting(function (ProjectDoc $doc): void {
            foreach ($doc->attachments as $attachment) {
                $attachment->delete(); // removes stored files
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocRevision::class, 'doc_id')->latest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'doc_id');
    }

    public function excerpt(int $limit = 140): string
    {
        return HtmlSanitizer::text($this->content, $limit);
    }

    public function wordCount(): int
    {
        $text = HtmlSanitizer::text($this->content);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }
}
