<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\CalendarView;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The project calendar: moving between months and adding a task on a day. */
class CalendarViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_that_is_not_a_calendar_date_is_rejected(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(CalendarView::class, ['project' => $project])
            ->set('addingOn', 'not-a-day')
            ->set('newTitle', 'Launch party')
            ->call('createOnDay')
            ->assertHasErrors('addingOn');

        $this->assertDatabaseMissing('tasks', ['title' => 'Launch party']);
    }

    public function test_task_added_on_a_day_is_due_that_day_in_the_first_section(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $first = Section::factory()->for($project)->create();
        Section::factory()->for($project)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(CalendarView::class, ['project' => $project])
            ->call('startAdding', '2026-11-12')
            ->set('newTitle', 'Launch party')
            ->call('createOnDay')
            ->assertHasNoErrors();

        $task = Task::where('title', 'Launch party')->sole();
        $this->assertSame($first->id, $task->section_id);
        $this->assertSame('2026-11-12', $task->due_date->toDateString());
    }

    public function test_moves_a_month_forward_and_back_and_returns_to_this_month(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(CalendarView::class, ['project' => $project])
            ->assertSet('month', '2026-10')
            ->call('shift', 3)->assertSet('month', '2027-01')
            ->call('shift', -4)->assertSet('month', '2026-09')
            ->call('today')->assertSet('month', '2026-10');
    }

    public function test_an_unreadable_month_in_the_link_falls_back_to_this_month(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::withQueryParams(['month' => 'soon'])->test(CalendarView::class, ['project' => $project])
            ->assertSet('month', '2026-10');
    }

    public function test_top_level_tasks_appear_on_their_due_day(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $task = Task::factory()->for($project)->dueOn('2026-11-12')->create(['title' => 'Launch party']);
        Task::factory()->subtaskOf($task)->dueOn('2026-11-12')->create(['title' => 'Buy balloons']);
        Task::factory()->for($project)->dueOn('2026-12-01')->create(['title' => 'Next month']);
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        $calendar = Livewire::withQueryParams(['month' => '2026-11'])->test(CalendarView::class, ['project' => $project]);

        $this->assertSame(['Launch party'], $calendar->viewData('tasks')->get('2026-11-12')->pluck('title')->all());
        $calendar->assertSee('Launch party')->assertDontSee('Buy balloons');
    }
}
