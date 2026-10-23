<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\Show;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The project page header: details dialog and archiving. */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dialog_opened_before_another_change_keeps_that_change(): void
    {
        [$admin, $project] = $this->project('admin');
        $this->actingAs($admin);
        $page = Livewire::test(Show::class, ['project' => $project])->call('openEdit');
        Project::whereKey($project->id)->update(['privacy' => 'private']);

        $page->set('form.name', 'Renamed')->call('saveEdit')->assertHasNoErrors();

        $project->refresh();
        $this->assertSame('Renamed', $project->name);
        $this->assertSame('private', $project->privacy);
    }

    public function test_archiving_twice_archives_once(): void
    {
        [$admin, $project] = $this->project('admin');
        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])->call('archive', true)->call('archive', true);

        $this->assertNotNull($project->fresh()->archived_at);
        $this->assertSame(1, AuditLog::where('action', 'project.archived')->count());
    }

    public function test_a_member_cannot_archive(): void
    {
        [$member, $project] = $this->project('member');
        $this->actingAs($member);

        Livewire::test(Show::class, ['project' => $project])->call('archive', true)->assertForbidden();

        $this->assertNull($project->fresh()->archived_at);
    }

    /**
     * @return array{User, Project}
     */
    private function project(string $role): array
    {
        $workspace = Workspace::factory()->create();

        return [User::factory()->inWorkspace($workspace, $role)->create(), Project::factory()->for($workspace)->create()];
    }
}
