<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** Groups shown in the audit log filter. */
    public const GROUPS = [
        'auth' => 'Sign-ins & security',
        'member' => 'People & roles',
        'invitation' => 'Invitations',
        'team' => 'Teams',
        'client' => 'Clients & portal access',
        'project' => 'Projects',
        'workspace' => 'Workspace settings',
        'github' => 'GitHub',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
