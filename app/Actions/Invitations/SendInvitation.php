<?php

namespace App\Actions\Invitations;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Creates an invitation and emails it. An earlier pending invitation for the same address is
 * replaced in the same step, so one address never has two invitations with different roles.
 * The caller can show ->url() so admins can share the link by hand too.
 */
class SendInvitation
{
    /**
     * @param array<int, int|string> $teamIds teams an internal member joins on accepting
     */
    public function handle(Workspace $workspace, User $by, string $email, string $role, ?int $clientId = null, array $teamIds = []): Invitation
    {
        $email = strtolower(trim($email));
        RateLimiter::hit('invites:'.$workspace->id, 3600);

        $invitation = DB::transaction(function () use ($workspace, $by, $email, $role, $clientId, $teamIds): Invitation {
            Workspace::whereKey($workspace->id)->lockForUpdate()->first();
            $workspace->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

            return $workspace->invitations()->create([
                'email' => $email,
                'role' => $role,
                'client_id' => $clientId,
                'team_ids' => array_values(array_map('intval', $teamIds)) ?: null,
                'invited_by' => $by->id,
            ]);
        });

        try {
            Mail::to($email)->send(new InvitationMail($invitation->load('workspace', 'inviter', 'client')));
        } catch (Throwable $e) {
            Log::warning('Invitation email failed: '.$e->getMessage());
        }

        return $invitation;
    }
}
