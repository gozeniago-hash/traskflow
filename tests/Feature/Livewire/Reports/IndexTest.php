<?php

namespace Tests\Feature\Livewire\Reports;

use App\Livewire\Reports\Index;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Reports: periods, and members seeing only their own work. */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_period_that_is_not_in_the_menu_falls_back_to_14_days(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());

        Livewire::test(Index::class)->set('report', 'upcoming')->set('days', 5000)->assertSet('days', 14);
    }

    public function test_a_member_sees_only_their_own_tasks(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $member = User::factory()->inWorkspace($workspace)->create();
        $colleague = User::factory()->inWorkspace($workspace)->create();
        Task::factory()->for($project)->assignedTo($member)->create();
        Task::factory()->for($project)->assignedTo($colleague)->count(2)->create();
        $this->actingAs($member);

        Livewire::withQueryParams(['person' => (string) $colleague->id])->test(Index::class)
            ->assertViewHas('data', fn (array $data) => $data['totals']['total'] === 1);
    }

    public function test_a_member_asking_for_the_projects_report_gets_their_own_tasks_instead(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(Index::class)->set('report', 'projects')
            ->assertSet('report', 'incomplete')
            ->assertViewHas('reports', fn (array $reports) => ! array_key_exists('projects', $reports));
    }
}
