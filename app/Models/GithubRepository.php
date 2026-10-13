<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GithubRepository extends Model
{
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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

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
