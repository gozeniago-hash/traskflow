<?php

namespace App\Models;

use Database\Factories\PullRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PullRequest extends BaseModel
{
    /** @use HasFactory<PullRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'number', 'title', 'url', 'state', 'draft', 'merged_at',
        'head_ref', 'author_login', 'author_avatar', 'gh_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'draft' => 'boolean',
            'merged_at' => 'datetime',
            'gh_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GithubRepository, $this> */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GithubRepository::class, 'github_repository_id');
    }

    /** @return BelongsToMany<Task, $this> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    /** open | draft | merged | closed */
    public function status(): string
    {
        return match (true) {
            $this->merged_at !== null => 'merged',
            $this->state === 'closed' => 'closed',
            $this->draft => 'draft',
            default => 'open',
        };
    }

    public const STATUS_STYLES = [
        'open' => ['label' => 'Open', 'class' => 'bg-emerald-50 text-emerald-700', 'icon' => 'text-emerald-600'],
        'draft' => ['label' => 'Draft', 'class' => 'bg-gray-100 text-ink-700', 'icon' => 'text-ink-500'],
        'merged' => ['label' => 'Merged', 'class' => 'bg-violet-50 text-violet-700', 'icon' => 'text-violet-600'],
        'closed' => ['label' => 'Closed', 'class' => 'bg-red-50 text-red-700', 'icon' => 'text-red-600'],
    ];
}
