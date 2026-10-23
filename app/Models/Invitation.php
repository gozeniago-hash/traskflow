<?php

namespace App\Models;

use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends BaseModel
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    protected $fillable = ['workspace_id', 'client_id', 'invited_by', 'email', 'role', 'team_ids', 'token', 'accepted_at', 'expires_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'expires_at' => 'datetime', 'team_ids' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            $invitation->token ??= Str::random(48);
            $invitation->expires_at ??= now()->addDays(14);
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at?->isFuture();
    }

    public function url(): string
    {
        return route('invitations.show', $this->token);
    }
}
