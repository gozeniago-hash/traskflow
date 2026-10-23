<?php

namespace Tests\Feature\Actions\Teams;

use App\Actions\Teams\RemoveTeamMember;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Taking someone out of a team. */
class RemoveTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_removes_and_logs_once(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->hasAttached($team)->create();
        $remove = app(RemoveTeamMember::class);

        $remove->handle($team, $member->id);
        $remove->handle($team, $member->id);

        $this->assertSame(0, $team->users()->count());
        $this->assertSame(1, AuditLog::where('action', 'team.member_removed')->count());
    }
}
