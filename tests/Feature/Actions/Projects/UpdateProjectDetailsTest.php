<?php

namespace Tests\Feature\Actions\Projects;

use App\Actions\Projects\UpdateProjectDetails;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Saving the project details dialog. */
class UpdateProjectDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_given_changes_are_saved(): void
    {
        $project = $this->project();
        Project::whereKey($project->id)->update(['privacy' => 'private']);

        app(UpdateProjectDetails::class)->handle($project, ['name' => 'Renamed']);

        $project->refresh();
        $this->assertSame('Renamed', $project->name);
        $this->assertSame('private', $project->privacy);
    }

    public function test_access_changes_are_logged(): void
    {
        $project = $this->project();

        app(UpdateProjectDetails::class)->handle($project, ['privacy' => 'private']);

        $this->assertSame(['project.access_changed'], AuditLog::pluck('action')->all());
    }

    public function test_a_new_name_alone_is_not_logged(): void
    {
        $project = $this->project();

        app(UpdateProjectDetails::class)->handle($project, ['name' => 'Renamed']);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_removing_the_client_turns_the_portal_off(): void
    {
        $project = $this->project();
        $project->update(['client_id' => Client::factory()->for($project->workspace)->create()->id, 'portal_enabled' => true]);

        app(UpdateProjectDetails::class)->handle($project, ['client_id' => null]);

        $project->refresh();
        $this->assertNull($project->client_id);
        $this->assertFalse($project->portal_enabled);
    }

    private function project(): Project
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());

        return Project::factory()->for($workspace)->create();
    }
}
