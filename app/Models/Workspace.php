<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends BaseModel
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    public const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'member' => 'Member',
        'client' => 'Client',
    ];

    /** What each internal role can do — shown when inviting people and changing roles. */
    public const ROLE_DESCRIPTIONS = [
        'admin' => 'Sees and manages everything, including workspace settings, people, teams and clients.',
        'manager' => 'Sees shared projects and fully manages the projects they belong to: details, members, fields, everyone\'s time and reports.',
        'member' => 'Works on projects shared with them. Can\'t change project settings or open workspace pages; sees only their own time and reports.',
    ];

    protected $fillable = ['name', 'owner_id', 'require_two_factor'];

    protected $hidden = ['github_token'];

    protected function casts(): array
    {
        return ['github_token' => 'encrypted', 'require_two_factor' => 'boolean'];
    }

    /** @return HasMany<GithubRepository, $this> */
    public function githubRepositories(): HasMany
    {
        return $this->hasMany(GithubRepository::class)->orderBy('full_name');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
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

    /** @return HasMany<Client, $this> */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** Bytes of uploaded files stored across all of this workspace's projects. */
    public function storageUsed(): int
    {
        return (int) Attachment::whereIn('project_id', $this->projects()->select('id'))->sum('size');
    }

    /** Allowance in bytes, or null when unlimited. */
    public function storageQuota(): ?int
    {
        $mb = (int) config('taskflow.workspace_storage_mb');

        return $mb > 0 ? $mb * 1024 * 1024 : null;
    }

    public function hasRoomFor(int $bytes): bool
    {
        $quota = $this->storageQuota();

        return $quota === null || $this->storageUsed() + $bytes <= $quota;
    }

    public function storageFullMessage(): string
    {
        $quota = $this->storageQuota();

        return 'This workspace has used its '.($quota >= 1024 ** 3 ? round($quota / 1024 ** 3, 1).' GB' : round($quota / 1024 ** 2).' MB')
            .' of file storage. Delete files you no longer need, or ask your admin for more space.';
    }
}
