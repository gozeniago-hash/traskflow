<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\Index;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The projects list and the "New project" form. */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_id_is_suggested_from_the_name_until_someone_types_one(): void
    {
        $this->asManager();

        $page = Livewire::test(Index::class)->call('openCreate')->set('form.name', 'Website Redesign');
        $page->assertSet('form.key', 'WR');

        $page->set('form.key', 'web-2')->set('form.name', 'Something else')->assertSet('form.key', 'WEB2');
    }

    public function test_creating_a_project_with_custom_fields_opens_it(): void
    {
        $this->asManager();

        Livewire::test(Index::class)->call('openCreate')
            ->set('form.name', 'Mobile App')->set('form.privacy', 'workspace')->set('form.layout', 'list')
            ->set('form.fields', [['name' => 'Budget', 'type' => 'currency', 'options' => '']])
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect();

        $project = Project::where('name', 'Mobile App')->sole();
        $this->assertSame('MA', $project->key);
        $this->assertSame(['Budget'], ProjectField::where('project_id', $project->id)->pluck('name')->all());
    }

    public function test_two_custom_fields_with_the_same_name_are_refused(): void
    {
        $this->asManager();

        $page = Livewire::test(Index::class)->call('openCreate')
            ->set('form.name', 'Mobile App')->set('form.privacy', 'workspace')
            ->set('form.fields', [['name' => 'Budget', 'type' => 'text', 'options' => ''], ['name' => 'budget', 'type' => 'text', 'options' => '']])
            ->call('create');

        $page->assertHasErrors('form.fields.1.name');
        $this->assertSame(0, Project::count());
    }

    public function test_search_filters_the_list_by_name(): void
    {
        $workspace = $this->asManager();
        Project::factory()->for($workspace)->create(['name' => 'Website Redesign']);
        Project::factory()->for($workspace)->create(['name' => 'Mobile App']);

        Livewire::test(Index::class)->set('search', 'Website')
            ->assertSee('Website Redesign')
            ->assertDontSee('Mobile App');
    }

    public function test_a_member_cannot_create_projects(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(Index::class)->set('form.name', 'Sneaky')->call('create')->assertForbidden();

        $this->assertSame(0, Project::count());
    }

    private function asManager(): Workspace
    {
        $workspace = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'manager')->create());

        return $workspace;
    }
}
