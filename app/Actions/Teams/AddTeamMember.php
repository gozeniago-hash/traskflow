<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use App\Support\Audit;

/** Puts someone in a team. Adding someone already in it changes and logs nothing. */
class AddTeamMember
{
    public function handle(Team $team, int $userId): void
    {
        if ($team->users()->syncWithoutDetaching([$userId])['attached']) {
            Audit::log('team.member_added', 'Added '.User::find($userId)?->name.' to team “'.$team->name.'”', $team, ['user_id' => $userId]);
        }
    }
}
