<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Home;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The Home page: my upcoming tasks and counts. */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_my_tasks_in_projects_i_can_still_open(): void
    {
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $open = Project::factory()->for($workspace)->create();
        $hidden = Project::factory()->for($workspace)->private()->create();
        Task::factory()->for($open)->assignedTo($me)->create(['title' => 'Visible task']);
        Task::factory()->for($hidden)->assignedTo($me)->create(['title' => 'Hidden task']);
        Task::factory()->for(Project::factory())->assignedTo($me)->create(['title' => 'Elsewhere task']);
        $this->actingAs($me);

        $home = Livewire::test(Home::class);

        $this->assertSame(['Visible task'], $home->viewData('upcoming')->pluck('title')->all());
        $this->assertSame(1, $home->viewData('stats')['open']);
    }

    public function test_counts_my_overdue_tasks(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $project = Project::factory()->for($workspace)->create();
        Task::factory()->for($project)->assignedTo($me)->dueOn('2026-10-19')->create();
        Task::factory()->for($project)->assignedTo($me)->dueOn('2026-10-20')->create();
        Task::factory()->for($project)->assignedTo($me)->dueOn('2026-10-18')->completed()->create();
        $this->actingAs($me);

        $stats = Livewire::test(Home::class)->viewData('stats');

        $this->assertSame(['open' => 2, 'overdue' => 1], ['open' => $stats['open'], 'overdue' => $stats['overdue']]);
    }

    public function test_completing_a_task_from_home_offers_undo(): void
    {
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for(Project::factory()->for($workspace))->assignedTo($me)->create();
        $this->actingAs($me);

        Livewire::test(Home::class)
            ->call('setComplete', $task->id, true)
            ->assertDispatched('toast', message: 'Marked '.$task->ticket().' complete.', type: 'success', undo: route('tasks.reopen', $task));

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_a_task_in_a_project_i_cannot_open_cannot_be_completed_from_home(): void
    {
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for(Project::factory()->for($workspace)->private())->create();
        $this->actingAs($me);

        Livewire::test(Home::class)->call('setComplete', $task->id, true)->assertForbidden();

        $this->assertNull($task->fresh()->completed_at);
    }
}
