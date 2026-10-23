<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\ListView;
use App\Models\Client;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** A project's List view: adding tasks and sections, filters, drag and drop. */
class ListViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_task_goes_to_the_end_of_its_section(): void
    {
        [$member, $project, $section] = $this->project();
        Task::factory()->inSection($section)->create();
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])
            ->set('newTask.'.$section->id, '  Write the brief  ')
            ->call('addTask', $section->id)
            ->assertSet('newTask.'.$section->id, '');

        $task = Task::where('title', 'Write the brief')->sole();
        $this->assertSame([$section->id, 2, $member->id], [$task->section_id, $task->position, $task->creator_id]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'created']);
    }

    public function test_a_blank_task_title_adds_nothing(): void
    {
        [$member, $project, $section] = $this->project();
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])
            ->set('newTask.'.$section->id, '   ')
            ->call('addTask', $section->id);

        $this->assertSame(0, Task::count());
    }

    public function test_a_long_task_title_is_cut_to_255_characters(): void
    {
        [$member, $project, $section] = $this->project();
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])
            ->set('newTask.'.$section->id, str_repeat('a', 300))
            ->call('addTask', $section->id);

        $this->assertSame(255, mb_strlen(Task::sole()->title));
    }

    public function test_a_client_cannot_add_tasks(): void
    {
        [, $project, $section] = $this->project();
        $client = User::factory()->inWorkspace($project->workspace, 'client', $project->client)->create();
        $this->actingAs($client);

        Livewire::test(ListView::class, ['project' => $project])
            ->set('newTask.'.$section->id, 'Sneaky')
            ->call('addTask', $section->id)
            ->assertForbidden();

        $this->assertSame(0, Task::count());
    }

    public function test_a_contact_of_another_client_cannot_open_the_list(): void
    {
        [, $project] = $this->project();
        $outsider = User::factory()->inWorkspace($project->workspace, 'client', Client::factory()->for($project->workspace)->create())->create();
        $this->actingAs($outsider);

        Livewire::test(ListView::class, ['project' => $project])->assertForbidden();
    }

    public function test_the_project_owner_can_rename_a_section(): void
    {
        [$member, $project, $section] = $this->project();
        $project->update(['owner_id' => $member->id]);
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])
            ->call('editSection', $section->id)
            ->assertSet('sectionName', $section->name)
            ->set('sectionName', 'Backlog')
            ->call('renameSection')
            ->assertSet('editingSection', null);

        $this->assertSame('Backlog', $section->fresh()->name);
    }

    public function test_a_member_who_does_not_own_the_project_cannot_rename_a_section(): void
    {
        [$member, $project, $section] = $this->project();
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])
            ->call('editSection', $section->id)
            ->assertForbidden();
    }

    /**
     * @return array<string, array{string, array<int, string>, array<int, string>}>
     */
    public static function filters(): array
    {
        return [
            'incomplete (the default)' => ['incomplete', ['Open mine', 'Open unassigned'], ['Done task']],
            'completed' => ['completed', ['Done task'], ['Open mine', 'Open unassigned']],
            'all' => ['all', ['Open mine', 'Open unassigned', 'Done task'], []],
        ];
    }

    /**
     * @param array<int, string> $shown
     * @param array<int, string> $hidden
     */
    #[DataProvider('filters')]
    public function test_the_completion_filter_shows_the_matching_tasks(string $filter, array $shown, array $hidden): void
    {
        [$member, $project, $section] = $this->project();
        Task::factory()->inSection($section)->assignedTo($member)->create(['title' => 'Open mine']);
        Task::factory()->inSection($section)->create(['title' => 'Open unassigned']);
        Task::factory()->inSection($section)->completed()->create(['title' => 'Done task']);
        $this->actingAs($member);

        $list = Livewire::test(ListView::class, ['project' => $project])->set('filter', $filter);

        foreach ($shown as $title) {
            $list->assertSee($title);
        }
        foreach ($hidden as $title) {
            $list->assertDontSee($title);
        }
    }

    public function test_the_assignee_filter_shows_my_tasks_or_unassigned_ones(): void
    {
        [$member, $project, $section] = $this->project();
        Task::factory()->inSection($section)->assignedTo($member)->create(['title' => 'Open mine']);
        Task::factory()->inSection($section)->create(['title' => 'Open unassigned']);
        Task::factory()->inSection($section)->assignedTo(User::factory()->inWorkspace($project->workspace)->create())->create(['title' => 'Open theirs']);
        $this->actingAs($member);
        $list = Livewire::test(ListView::class, ['project' => $project]);

        $list->set('assigneeFilter', 'me')->assertSee('Open mine')->assertDontSee('Open unassigned')->assertDontSee('Open theirs');
        $list->set('assigneeFilter', 'unassigned')->assertSee('Open unassigned')->assertDontSee('Open mine')->assertDontSee('Open theirs');
    }

    public function test_dragging_a_task_into_another_section_moves_it_there(): void
    {
        [$member, $project, $todo] = $this->project();
        $done = Section::factory()->for($project)->create(['name' => 'Done']);
        $dragged = Task::factory()->inSection($todo)->create();
        $already = Task::factory()->inSection($done)->create();
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])
            ->call('reorder', $done->id, [$dragged->id, $already->id], $dragged->id, $todo->id)
            ->assertNotDispatched('toast');

        $this->assertSame([$done->id, 1], [$dragged->fresh()->section_id, $dragged->fresh()->position]);
        $this->assertSame(2, $already->fresh()->position);
    }

    public function test_a_drag_from_an_out_of_date_page_is_refused_with_a_message(): void
    {
        [$member, $project, $todo] = $this->project();
        $doing = Section::factory()->for($project)->create(['name' => 'Doing']);
        $done = Section::factory()->for($project)->create(['name' => 'Done']);
        $dragged = Task::factory()->inSection($todo)->create();
        $this->actingAs($member);
        $list = Livewire::test(ListView::class, ['project' => $project]);
        $dragged->update(['section_id' => $doing->id]);

        $list->call('reorder', $done->id, [$dragged->id], $dragged->id, $todo->id)
            ->assertDispatched('toast', message: 'Someone else moved that task just now. The list shows where it is.', type: 'error', undo: null);

        $this->assertSame($doing->id, $dragged->fresh()->section_id);
    }

    public function test_the_last_section_cannot_be_deleted_while_it_holds_tasks(): void
    {
        [$member, $project, $section] = $this->project();
        $project->update(['owner_id' => $member->id]);
        Task::factory()->inSection($section)->create();
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])
            ->call('deleteSection', $section->id)
            ->assertDispatched('toast', message: 'A project needs at least one section to hold its tasks.', type: 'error', undo: null);

        $this->assertModelExists($section);
    }

    public function test_a_project_without_sections_gets_one_to_hold_its_tasks(): void
    {
        [$member, $project, $section] = $this->project();
        $section->delete();
        $this->actingAs($member);

        Livewire::test(ListView::class, ['project' => $project])->assertOk();

        $this->assertSame(['To do'], $project->sections()->pluck('name')->all());
    }

    /**
     * A workspace-wide portal project with one section, and a member.
     *
     * @return array{User, Project, Section}
     */
    private function project(): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->withPortal(Client::factory()->for($workspace)->create())->create();

        return [User::factory()->inWorkspace($workspace)->create(), $project, Section::factory()->for($project)->create(['name' => 'To do'])];
    }
}
