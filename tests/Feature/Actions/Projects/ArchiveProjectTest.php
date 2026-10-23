<?php

namespace Tests\Feature\Actions\Projects;

use App\Actions\Projects\ArchiveProject;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Archiving and restoring a project. */
class ArchiveProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_archives_and_restores(): void
    {
        $project = $this->project();
        $archive = app(ArchiveProject::class);

        $archived = $archive->handle($project, true);
        $restored = $archive->handle($project, false);

        $this->assertTrue($archived);
        $this->assertTrue($restored);
        $this->assertNull($project->fresh()->archived_at);
        $this->assertSame(['project.archived', 'project.restored'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_asking_for_the_state_it_is_already_in_changes_nothing(): void
    {
        $project = $this->project();

        $changed = app(ArchiveProject::class)->handle($project, false);

        $this->assertFalse($changed);
        $this->assertNull($project->fresh()->archived_at);
        $this->assertSame(0, AuditLog::count());
    }

    private function project(): Project
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());

        return Project::factory()->for($workspace)->create();
    }
}
