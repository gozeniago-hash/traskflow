<?php

namespace Tests\Feature\Actions\Teams;

use App\Actions\Teams\AddTeamMember;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Putting someone in a team. */
class AddTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_and_logs_once_however_often_it_is_asked(): void
    {
        $team = Team::factory()->create();
        $person = User::factory()->create();
        $add = app(AddTeamMember::class);

        $add->handle($team, $person->id);
        $add->handle($team, $person->id);

        $this->assertSame([$person->id], $team->users()->pluck('users.id')->all());
        $this->assertSame(1, AuditLog::where('action', 'team.member_added')->count());
    }
}
