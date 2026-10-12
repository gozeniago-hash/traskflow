<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['workspace_id', 'name', 'contact_name', 'email', 'phone', 'website', 'notes'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Portal users (contacts) who belong to this client. */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot(['role', 'workspace_id'])
            ->wherePivot('role', 'client');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
