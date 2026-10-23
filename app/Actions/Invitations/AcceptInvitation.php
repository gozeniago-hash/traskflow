<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Joins a person to the workspace of an invitation. An invitation works once: it is locked
 * while it is used, so a double submit, or an admin revoking or re-sending it at the same
 * moment, can't let it through twice. Someone already in the workspace keeps their role;
 * the invitation only adds its teams.
 */
class AcceptInvitation
{
    /**
     * Pass the existing account, or the name and password for a new one.
     *
     * @param array{name: string, password: string}|null $newAccount
     * @return User|null null when the invitation was already used, revoked or expired
     */
    public function handle(Invitation $invitation, ?User $user, ?array $newAccount = null): ?User
    {
        return DB::transaction(function () use ($invitation, $user, $newAccount): ?User {
            $current = Invitation::whereKey($invitation->id)->lockForUpdate()->first();
            if (! $current?->isUsable()) {
                return null;
            }

            $user ??= User::create([...$newAccount, 'email' => $current->email]);

            // The invitation link went to this address, so it counts as verified.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $workspace = $current->workspace;
            if (! $workspace->users()->whereKey($user->id)->exists()) {
                $workspace->users()->attach($user->id, ['role' => $current->role, 'client_id' => $current->client_id]);
            }

            // Internal members join only the teams the admin picked when inviting.
            if ($current->role !== 'client' && $current->team_ids) {
                foreach ($workspace->teams()->whereKey($current->team_ids)->get() as $team) {
                    $team->users()->syncWithoutDetaching([$user->id]);
                }
            }

            $current->forceFill(['accepted_at' => now()])->save();
            $user->forceFill(['current_workspace_id' => $current->workspace_id])->save();
            $user->forgetMembership();

            return $user;
        });
    }
}
