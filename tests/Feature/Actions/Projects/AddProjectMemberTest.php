<?php

namespace Tests\Feature\Actions\Projects;

use App\Actions\Projects\AddProjectMember;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Adding someone to a project. */
class AddProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_and_logs_once_however_often_it_is_asked(): void
    {
        $project = Project::factory()->create();
        $person = User::factory()->create();
        $add = app(AddProjectMember::class);

        $first = $add->handle($project, $person);
        $second = $add->handle($project, $person);

        $this->assertSame([true, false], [$first, $second]);
        $this->assertSame([$person->id], $project->members()->pluck('users.id')->all());
        $this->assertSame(1, AuditLog::where('action', 'project.member_added')->count());
    }
}
