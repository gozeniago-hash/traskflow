<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A pinned project reference on the Overview: an external link or an uploaded file. */
class ProjectResource extends Model
{
    protected $fillable = ['user_id', 'title', 'url', 'attachment_id', 'client_visible', 'position'];

    protected function casts(): array
    {
        return ['client_visible' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Removing a file resource removes the stored file as well.
        static::deleted(function (ProjectResource $resource) {
            $resource->attachment?->delete();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function isFile(): bool
    {
        return $this->attachment_id !== null;
    }

    public function domain(): string
    {
        $host = (string) parse_url((string) $this->url, PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host) ?: (string) $this->url;
    }

    /** Recognisable label for common services, e.g. "Figma" or "Google Docs". */
    public function service(): ?string
    {
        $host = $this->domain();
        foreach ([
            'figma.com' => 'Figma', 'docs.google.com' => 'Google Docs', 'drive.google.com' => 'Google Drive',
            'sheets.google.com' => 'Google Sheets', 'github.com' => 'GitHub', 'notion.so' => 'Notion', 'notion.site' => 'Notion',
            'miro.com' => 'Miro', 'loom.com' => 'Loom', 'dropbox.com' => 'Dropbox', 'canva.com' => 'Canva',
            'youtube.com' => 'YouTube', 'youtu.be' => 'YouTube', 'sharepoint.com' => 'SharePoint', 'onedrive.live.com' => 'OneDrive',
        ] as $suffix => $label) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return $label;
            }
        }

        return null;
    }
}
