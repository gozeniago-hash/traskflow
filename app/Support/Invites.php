<?php

namespace App\Support;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Invites
{
    /** Invitation emails one workspace may send per hour. */
    public const PER_HOUR = 50;

    /** How many more invitations this workspace may send this hour. */
    public static function remaining(Workspace $workspace): int
    {
        return \Illuminate\Support\Facades\RateLimiter::remaining('invites:'.$workspace->id, self::PER_HOUR);
    }

    /**
     * Create (or refresh) an invitation and email it. Returns the invitation;
     * the caller can show ->url() so admins can share it manually too.
     */
    public static function send(Workspace $workspace, User $by, string $email, string $role, ?int $clientId = null, array $teamIds = []): Invitation
    {
        $email = strtolower(trim($email));
        \Illuminate\Support\Facades\RateLimiter::hit('invites:'.$workspace->id, 3600);

        $workspace->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

        $invitation = $workspace->invitations()->create([
            'email' => $email,
            'role' => $role,
            'client_id' => $clientId,
            'team_ids' => array_values(array_map('intval', $teamIds)) ?: null,
            'invited_by' => $by->id,
        ]);

        try {
            Mail::to($email)->send(new InvitationMail($invitation->load('workspace', 'inviter', 'client')));
        } catch (\Throwable $e) {
            Log::warning('Invitation email failed: '.$e->getMessage());
        }

        return $invitation;
    }
}
