<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Teams;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The teams page: who is in which team, which decides who sees team projects. */
class TeamsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function refusedRoles(): array
    {
        return ['manager' => ['manager'], 'member' => ['member']];
    }

    #[DataProvider('refusedRoles')]
    public function test_people_who_are_not_admins_cannot_open_teams(string $role): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs(User::factory()->inWorkspace($workspace, $role)->create())->get(route('teams'));

        $response->assertForbidden();
    }

    public function test_client_contacts_are_sent_to_their_portal(): void
    {
        $workspace = Workspace::factory()->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create();

        $response = $this->actingAs($contact)->get(route('teams'));

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_a_new_team_includes_the_admin_who_made_it(): void
    {
        [$admin, $workspace] = $this->admin();

        Livewire::actingAs($admin)->test(Teams::class)->call('create')
            ->set('name', 'Design')->set('description', 'Brand and UI')->call('save')
            ->assertHasNoErrors()->assertSet('showForm', false);

        $team = Team::sole();
        $this->assertSame($workspace->id, $team->workspace_id);
        $this->assertSame([$admin->id], $team->users()->pluck('users.id')->all());
        $this->assertSame(['team.created'], AuditLog::pluck('action')->all());
    }

    public function test_a_team_needs_a_name_and_a_short_description(): void
    {
        [$admin] = $this->admin();
        $page = Livewire::actingAs($admin)->test(Teams::class)->call('create');

        $page->set('name', '')->call('save')->assertHasErrors(['name' => 'required']);
        $page->set('name', 'Design')->set('description', str_repeat('a', 501))->call('save')->assertHasErrors(['description' => 'max']);

        $this->assertSame(0, Team::count());
    }

    public function test_editing_a_team_saves_and_logs_it(): void
    {
        [$admin, $workspace] = $this->admin();
        $team = Team::factory()->for($workspace)->create(['name' => 'Design']);

        Livewire::actingAs($admin)->test(Teams::class)->call('edit', $team->id)->set('name', 'Product design')->call('save');

        $this->assertSame('Product design', $team->fresh()->name);
        $this->assertSame(['team.updated'], AuditLog::pluck('action')->all());
    }

    public function test_a_team_of_another_workspace_cannot_be_edited(): void
    {
        [$admin] = $this->admin();
        $elsewhere = Team::factory()->create();

        Livewire::actingAs($admin)->test(Teams::class)->call('edit', $elsewhere->id)->assertNotFound();
    }

    public function test_only_internal_members_can_be_added_to_a_team(): void
    {
        [$admin, $workspace] = $this->admin();
        $team = Team::factory()->for($workspace)->create();
        $member = User::factory()->inWorkspace($workspace)->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create();
        $page = Livewire::actingAs($admin)->test(Teams::class);

        $page->set('addUser.'.$team->id, (string) $member->id)->call('addMember', $team->id);
        $page->set('addUser.'.$team->id, (string) $contact->id)->call('addMember', $team->id);

        $this->assertSame([$member->id], $team->users()->pluck('users.id')->all());
        $this->assertSame(['team.member_added'], AuditLog::pluck('action')->all());
    }

    public function test_removing_someone_from_a_team_is_logged_only_when_they_were_in_it(): void
    {
        [$admin, $workspace] = $this->admin();
        $team = Team::factory()->for($workspace)->create();
        $inTeam = User::factory()->inWorkspace($workspace)->hasAttached($team)->create();
        $notInTeam = User::factory()->inWorkspace($workspace)->create();
        $page = Livewire::actingAs($admin)->test(Teams::class);

        $page->call('removeMember', $team->id, $inTeam->id);
        $page->call('removeMember', $team->id, $notInTeam->id);

        $this->assertSame(0, $team->users()->count());
        $this->assertSame(['team.member_removed'], AuditLog::pluck('action')->all());
    }

    public function test_deleting_a_team_keeps_its_projects(): void
    {
        [$admin, $workspace] = $this->admin();
        $team = Team::factory()->for($workspace)->create();
        $project = Project::factory()->forTeam($team)->create();

        Livewire::actingAs($admin)->test(Teams::class)->call('delete', $team->id);

        $this->assertModelMissing($team);
        $this->assertNull($project->fresh()->team_id);
        $this->assertSame(['team.deleted'], AuditLog::pluck('action')->all());
    }

    /**
     * @return array{User, Workspace}
     */
    private function admin(): array
    {
        $workspace = Workspace::factory()->create();

        return [User::factory()->inWorkspace($workspace, 'admin')->create(), $workspace];
    }
}
