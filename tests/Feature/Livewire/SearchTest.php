<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Search;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Searching projects, docs and tasks. */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_exact_ticket_number_goes_straight_to_the_task(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create(['key' => 'WEB']);
        $task = Task::factory()->for($project)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::withQueryParams(['q' => ' web-'.$task->number.' '])->test(Search::class)
            ->assertRedirect($task->ticketUrl());
    }

    public function test_a_ticket_number_in_a_project_i_cannot_open_does_not_redirect(): void
    {
        $workspace = Workspace::factory()->create();
        $task = Task::factory()->for(Project::factory()->for($workspace)->private()->create(['key' => 'SEC']))->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::withQueryParams(['q' => 'SEC-'.$task->number])->test(Search::class)
            ->assertNoRedirect()
            ->assertViewHas('ticket', null);
    }

    public function test_finds_only_what_i_can_open(): void
    {
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $open = Project::factory()->for($workspace)->create(['name' => 'Rocket launch']);
        $otherTeam = Project::factory()->forTeam(Team::factory()->for($workspace)->create())->create(['name' => 'Rocket secret']);
        $elsewhere = Project::factory()->create(['name' => 'Rocket elsewhere']);
        foreach ([$open, $otherTeam, $elsewhere] as $project) {
            Task::factory()->for($project)->create(['title' => 'Rocket task in '.$project->name]);
            ProjectDoc::factory()->for($project)->create(['title' => 'Rocket doc in '.$project->name]);
        }
        $this->actingAs($me);

        $search = Livewire::withQueryParams(['q' => 'rocket'])->test(Search::class);

        $this->assertSame(['Rocket launch'], $search->viewData('projects')->pluck('name')->all());
        $this->assertSame(['Rocket task in Rocket launch'], $search->viewData('tasks')->pluck('title')->all());
        $this->assertSame(['Rocket doc in Rocket launch'], $search->viewData('docs')->pluck('title')->all());
    }

    public function test_task_titles_in_results_are_escaped(): void
    {
        $workspace = Workspace::factory()->create();
        Task::factory()->for(Project::factory()->for($workspace))->create(['title' => 'Rocket <script>alert(1)</script>']);
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::withQueryParams(['q' => 'rocket'])->test(Search::class)
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    public function test_an_empty_search_shows_no_results(): void
    {
        $workspace = Workspace::factory()->create();
        Task::factory()->for(Project::factory()->for($workspace))->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        $search = Livewire::test(Search::class);

        $this->assertSame(0, $search->viewData('tasks')->count());
        $this->assertSame(0, $search->viewData('projects')->count());
    }
}
