<?php

namespace App\Models;

use Database\Factories\StatusUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusUpdate extends BaseModel
{
    /** @use HasFactory<StatusUpdateFactory> */
    use HasFactory;

    protected $fillable = ['project_id', 'user_id', 'status', 'title', 'body', 'client_visible'];

    protected function casts(): array
    {
        return ['client_visible' => 'boolean'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
