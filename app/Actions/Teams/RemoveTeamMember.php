<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use App\Support\Audit;

/** Takes someone out of a team. Removing someone who isn't in it changes and logs nothing. */
class RemoveTeamMember
{
    public function handle(Team $team, int $userId): void
    {
        if ($team->users()->detach($userId)) {
            Audit::log('team.member_removed', 'Removed '.User::find($userId)?->name.' from team “'.$team->name.'”', $team, ['user_id' => $userId]);
        }
    }
}
