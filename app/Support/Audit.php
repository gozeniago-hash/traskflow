<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records who did what: sign-ins, security changes and admin actions.
 * Entries can't be edited or deleted from the app.
 */
class Audit
{
    public static function log(string $action, string $description, ?Model $subject = null, array $meta = [], ?User $actor = null, ?int $workspaceId = null): void
    {
        try {
            $actor ??= auth()->user();
            $request = request();
            AuditLog::create([
                'workspace_id' => $workspaceId ?? $actor?->current_workspace_id,
                'user_id' => $actor?->id,
                'action' => $action,
                'description' => Str::limit($description, 495),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'meta' => $meta ?: null,
                'ip' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the action being audited.
            Log::warning('Audit log write failed: '.$e->getMessage());
        }
    }
}
