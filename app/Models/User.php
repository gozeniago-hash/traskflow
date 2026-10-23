<?php

namespace App\Models;

use App\Models\Concerns\ClearsBuilderCache;
use App\Models\Concerns\LogsDatabaseChanges;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use ClearsBuilderCache, HasFactory, LogsDatabaseChanges, Notifiable;

    public const AVATAR_COLORS = ['#b4685c', '#4e6a9e', '#5e8c6e', '#80659e', '#a9555e', '#b07d3e', '#3f8a80', '#7570a8', '#9a6390', '#5e8c5f'];

    protected $fillable = ['name', 'email', 'password', 'title', 'avatar_color', 'current_workspace_id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Cached pivot row for the current workspace (per request). */
    protected $membershipCache = false;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'notifications_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->avatar_color ??= self::AVATAR_COLORS[array_rand(self::AVATAR_COLORS)];
        });
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')->withPivot(['role', 'client_id'])->withTimestamps();
    }

    /** @return BelongsTo<Workspace, $this> */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /** @return HasMany<Task, $this> */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    // --------------------------------------------------------------- workspace

    public function membership(): ?object
    {
        if ($this->membershipCache === false) {
            $this->membershipCache = $this->current_workspace_id
                ? $this->workspaces()->where('workspaces.id', $this->current_workspace_id)->first()?->pivot
                : null;
        }

        return $this->membershipCache;
    }

    public function forgetMembership(): void
    {
        $this->membershipCache = false;
    }

    public function role(): ?string
    {
        return $this->membership()?->role;
    }

    public function isClient(): bool
    {
        return $this->role() === 'client';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role(), ['owner', 'admin'], true);
    }

    public function isManager(): bool
    {
        return $this->role() === 'manager';
    }

    /** Owners, admins and managers can create projects; members and clients can't. */
    public function canCreateProjects(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function isOwner(): bool
    {
        return $this->role() === 'owner';
    }

    public function clientId(): ?int
    {
        return $this->membership()?->client_id;
    }

    public function switchWorkspace(Workspace $workspace): bool
    {
        if (! $this->workspaces()->whereKey($workspace->id)->exists()) {
            return false;
        }

        $this->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->forgetMembership();

        return true;
    }

    // ------------------------------------------------------------------ helpers

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
    }

    public function firstName(): string
    {
        return Str::before($this->name, ' ') ?: $this->name;
    }

    // ---------------------------------------------------------- two-step verification

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /** Ten single-use codes for when the phone isn't at hand. */
    public static function newRecoveryCodes(): array
    {
        return collect(range(1, 10))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }

    /** Email verification is on when the app can actually send email (see config/taskflow.php). */
    public function needsToVerifyEmail(): bool
    {
        return config('taskflow.verify_email') && ! $this->hasVerifiedEmail();
    }
}
