<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    public const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'member' => 'Member',
        'client' => 'Client',
    ];

    protected $fillable = ['name', 'owner_id'];

    protected $hidden = ['github_token'];

    protected function casts(): array
    {
        return ['github_token' => 'encrypted'];
    }

    public function githubRepositories(): HasMany
    {
        return $this->hasMany(GithubRepository::class)->orderBy('full_name');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot(['role', 'client_id'])->withTimestamps();
    }

    /** Internal people (everyone except client contacts). */
    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('role', '!=', 'client');
    }

    public function clientUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'client');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
