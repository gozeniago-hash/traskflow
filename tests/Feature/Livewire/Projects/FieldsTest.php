<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\Fields;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Custom fields of a project. */
class FieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dropdown_needs_at_least_one_option(): void
    {
        $project = $this->asAdmin();

        $page = Livewire::test(Fields::class, ['project' => $project])->call('create')
            ->set('form.name', 'Stage')->set('form.type', 'select')->call('save');

        $this->assertSame(['Add at least one option.'], $page->errors()->get('newOption'));
        $this->assertSame(0, ProjectField::count());
    }

    public function test_a_dropdown_is_saved_with_its_options(): void
    {
        $project = $this->asAdmin();

        Livewire::test(Fields::class, ['project' => $project])->call('create')
            ->set('form.name', 'Stage')->set('form.type', 'select')
            ->set('newOption', 'Draft')->call('addOption')->set('newOption', 'Final')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(['Draft', 'Final'], array_column(ProjectField::sole()->options, 'label'));
    }

    public function test_two_fields_cannot_share_a_name_whatever_the_case(): void
    {
        $project = $this->asAdmin();
        ProjectField::factory()->for($project)->create(['name' => 'Budget']);

        $page = Livewire::test(Fields::class, ['project' => $project])->call('create')
            ->set('form.name', 'BUDGET')->set('form.type', 'text')->call('save');

        $this->assertSame(['This project already has a field with that name.'], $page->errors()->get('form.name'));
        $this->assertSame(1, ProjectField::count());
    }

    public function test_the_type_cannot_change_after_the_field_is_created(): void
    {
        $project = $this->asAdmin();
        $field = ProjectField::factory()->for($project)->create(['name' => 'Notes']);

        Livewire::test(Fields::class, ['project' => $project])->call('edit', $field->id)
            ->set('form.type', 'number')->set('form.name', 'Notes!')->call('save')->assertHasNoErrors();

        $field->refresh();
        $this->assertSame('text', $field->type);
        $this->assertSame('Notes!', $field->name);
    }

    public function test_fields_can_be_moved_and_deleted(): void
    {
        $project = $this->asAdmin();
        $first = ProjectField::factory()->for($project)->create();
        $second = ProjectField::factory()->for($project)->create();
        $page = Livewire::test(Fields::class, ['project' => $project]);

        $page->call('move', $first->id, 1);
        $this->assertSame([$second->id, $first->id], $project->fields()->pluck('id')->all());

        $page->call('delete', $second->id);
        $this->assertModelMissing($second);
    }

    public function test_a_member_cannot_open_a_field_for_editing(): void
    {
        $project = $this->asAdmin();
        $field = ProjectField::factory()->for($project)->create();
        $member = User::factory()->inWorkspace($project->workspace)->create();
        $page = Livewire::test(Fields::class, ['project' => $project]);
        $this->actingAs($member);

        $page->call('edit', $field->id)->assertForbidden();
    }

    private function asAdmin(): Project
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());

        return Project::factory()->for($workspace)->create();
    }
}
