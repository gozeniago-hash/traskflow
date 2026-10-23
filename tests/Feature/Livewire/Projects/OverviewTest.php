<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\Overview;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\StatusUpdate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The project overview: status updates, project members and key resources. */
class OverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_status_update_needs_a_title_of_at_most_190_characters(): void
    {
        [$admin, $project] = $this->admin();
        $this->actingAs($admin);

        Livewire::test(Overview::class, ['project' => $project])->set('updateTitle', '')->call('postUpdate')->assertHasErrors(['updateTitle' => 'required']);
        Livewire::test(Overview::class, ['project' => $project])->set('updateTitle', str_repeat('a', 191))->call('postUpdate')->assertHasErrors(['updateTitle' => 'max']);

        $this->assertSame(0, StatusUpdate::count());
    }

    public function test_posting_a_status_update_sets_the_project_status(): void
    {
        [$admin, $project] = $this->admin();
        $this->actingAs($admin);

        Livewire::test(Overview::class, ['project' => $project])
            ->set('updateStatus', 'at_risk')->set('updateTitle', 'Waiting on copy')->call('postUpdate')->assertHasNoErrors();

        $this->assertSame('at_risk', $project->fresh()->status);
        $this->assertSame('Waiting on copy', StatusUpdate::sole()->title);
    }

    public function test_only_the_author_or_an_admin_deletes_a_status_update(): void
    {
        [$admin, $project] = $this->admin();
        $manager = User::factory()->inWorkspace($project->workspace, 'manager')->hasAttached($project)->create();
        $update = StatusUpdate::factory()->for($project)->create(['user_id' => $admin->id]);
        $this->actingAs($manager);

        Livewire::test(Overview::class, ['project' => $project])->call('deleteUpdate', $update->id)->assertForbidden();
        $this->assertModelExists($update);

        $this->actingAs($admin);
        Livewire::test(Overview::class, ['project' => $project])->call('deleteUpdate', $update->id);
        $this->assertModelMissing($update);
    }

    public function test_adding_the_same_member_twice_is_logged_once(): void
    {
        [$admin, $project] = $this->admin();
        $member = User::factory()->inWorkspace($project->workspace)->create();
        $this->actingAs($admin);
        $page = Livewire::test(Overview::class, ['project' => $project]);

        $page->set('addMember', (string) $member->id)->call('addProjectMember')
            ->set('addMember', (string) $member->id)->call('addProjectMember');

        $this->assertTrue($project->members()->whereKey($member->id)->exists());
        $this->assertSame(1, AuditLog::where('action', 'project.member_added')->count());
    }

    public function test_someone_from_another_workspace_cannot_be_added(): void
    {
        [$admin, $project] = $this->admin();
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create())->create();
        $this->actingAs($admin);

        Livewire::test(Overview::class, ['project' => $project])->set('addMember', (string) $outsider->id)->call('addProjectMember');

        $this->assertFalse($project->members()->whereKey($outsider->id)->exists());
    }

    public function test_removing_a_member_is_logged(): void
    {
        [$admin, $project] = $this->admin();
        $member = User::factory()->inWorkspace($project->workspace)->hasAttached($project)->create();
        $this->actingAs($admin);

        Livewire::test(Overview::class, ['project' => $project])->call('removeProjectMember', $member->id);

        $this->assertFalse($project->members()->whereKey($member->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.member_removed']);
    }

    public function test_a_link_without_a_scheme_gets_https(): void
    {
        [$admin, $project] = $this->admin();
        $this->actingAs($admin);

        Livewire::test(Overview::class, ['project' => $project])->set('resourceUrl', 'figma.com/file/abc')->set('resourceTitle', 'Designs')->call('addLink')->assertHasNoErrors();

        $this->assertSame('https://figma.com/file/abc', ProjectResource::sole()->url);
    }

    public function test_a_javascript_link_is_refused(): void
    {
        [$admin, $project] = $this->admin();
        $this->actingAs($admin);

        Livewire::test(Overview::class, ['project' => $project])->set('resourceUrl', 'javascript:alert(1)')->call('addLink')->assertHasErrors('resourceUrl');

        $this->assertSame(0, ProjectResource::count());
    }

    public function test_resources_can_be_renamed_and_moved(): void
    {
        [$admin, $project] = $this->admin();
        $first = ProjectResource::factory()->for($project)->create(['title' => 'One']);
        $second = ProjectResource::factory()->for($project)->create(['title' => 'Two']);
        $this->actingAs($admin);

        Livewire::test(Overview::class, ['project' => $project])->call('renameResource', $first->id, ' Brief ')->call('moveResource', $first->id, 1);

        $this->assertSame('Brief', $first->fresh()->title);
        $this->assertSame([$second->id, $first->id], $project->resources()->pluck('id')->all());
    }

    public function test_a_removed_resource_can_be_undone(): void
    {
        [$admin, $project] = $this->admin();
        $resource = ProjectResource::factory()->for($project)->create();
        $this->actingAs($admin);

        $undoUrl = null;
        Livewire::test(Overview::class, ['project' => $project])->call('removeResource', $resource->id)
            ->assertDispatched('toast', function (string $event, array $params) use (&$undoUrl): bool {
                $undoUrl = $params['undo'];

                return $undoUrl !== null;
            });
        $this->assertModelMissing($resource);

        $this->postJson($undoUrl)->assertOk();

        $this->assertModelExists($resource);
    }

    public function test_a_member_cannot_change_the_overview(): void
    {
        [, $project] = $this->admin();
        $this->actingAs(User::factory()->inWorkspace($project->workspace)->create());

        Livewire::test(Overview::class, ['project' => $project])->set('resourceUrl', 'https://example.com')->call('addLink')->assertForbidden();

        $this->assertSame(0, ProjectResource::count());
    }

    /**
     * @return array{User, Project}
     */
    private function admin(): array
    {
        $workspace = Workspace::factory()->create();

        return [User::factory()->inWorkspace($workspace, 'admin')->create(), Project::factory()->for($workspace)->create()->fresh()];
    }
}
