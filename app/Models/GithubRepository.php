<?php

namespace App\Models;

use Database\Factories\GithubRepositoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GithubRepository extends BaseModel
{
    /** @use HasFactory<GithubRepositoryFactory> */
    use HasFactory;

    protected $fillable = ['full_name', 'webhook_secret', 'webhook_id', 'last_synced_at', 'last_webhook_at', 'last_error'];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'webhook_secret' => 'encrypted',
            'last_synced_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return HasMany<PullRequest, $this> */
    public function pullRequests(): HasMany
    {
        return $this->hasMany(PullRequest::class);
    }

    public function webhookUrl(): string
    {
        return route('github.webhook', $this);
    }

    public function htmlUrl(): string
    {
        return 'https://github.com/'.$this->full_name;
    }
}
