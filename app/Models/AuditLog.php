<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['workspace_id', 'user_id', 'action', 'description', 'subject_type', 'subject_id', 'meta', 'ip', 'user_agent'];

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
