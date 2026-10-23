<?php

namespace Tests\Feature\Actions\Projects;

use App\Actions\Projects\CreateProject;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Creating a project with its starter sections and custom fields. */
class CreateProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_board_project_gets_four_starter_sections_and_its_owner_as_member(): void
    {
        [$workspace, $owner] = $this->manager();

        $project = app(CreateProject::class)->handle($workspace, $owner, $this->details(['layout' => 'board']));

        $this->assertSame(['To do', 'In progress', 'In review', 'Done'], $project->sections()->pluck('name')->all());
        $this->assertSame($owner->id, $project->owner_id);
        $this->assertTrue($project->members()->whereKey($owner->id)->exists());
        $this->assertSame('board', $project->default_view);
    }

    public function test_the_portal_stays_off_without_a_client(): void
    {
        [$workspace, $owner] = $this->manager();

        $project = app(CreateProject::class)->handle($workspace, $owner, $this->details(['portal_enabled' => true]));

        $this->assertFalse($project->portal_enabled);
    }

    public function test_custom_fields_are_created_and_a_dropdown_without_options_becomes_text(): void
    {
        [$workspace, $owner] = $this->manager();

        $project = app(CreateProject::class)->handle($workspace, $owner, $this->details(), [
            ['name' => ' Budget ', 'type' => 'currency'],
            ['name' => 'Stage', 'type' => 'select', 'options' => ''],
            ['name' => 'Size', 'type' => 'select', 'options' => 'S, M, L'],
        ]);

        $fields = $project->fields()->get();
        $this->assertSame(['Budget', 'Stage', 'Size'], $fields->pluck('name')->all());
        $this->assertSame(['currency', 'text', 'select'], $fields->pluck('type')->all());
        $this->assertSame(['S', 'M', 'L'], array_column($fields[2]->options, 'label'));
    }

    public function test_an_automatic_id_taken_at_the_same_moment_is_picked_again(): void
    {
        [$workspace, $owner] = $this->manager();
        $this->takeTheIdOnce();

        $project = app(CreateProject::class)->handle($workspace, $owner, $this->details(['name' => 'Website']));

        $this->assertModelExists($project);
        $this->assertSame(1, Project::where('name', 'Website')->count());
    }

    public function test_a_typed_id_taken_at_the_same_moment_gives_a_normal_error(): void
    {
        [$workspace, $owner] = $this->manager();
        $this->takeTheIdOnce();

        try {
            app(CreateProject::class)->handle($workspace, $owner, $this->details(['name' => 'Website', 'key' => 'WEB']));
            $this->fail('The project was created with an ID that was already taken.');
        } catch (ValidationException $e) {
            $this->assertSame(['Another project in this workspace already uses that ID.'], $e->errors()['form.key']);
        }

        $this->assertSame(0, Project::where('name', 'Website')->count());
    }

    /** The first project about to be saved finds its ID taken by a project created a moment earlier. */
    private function takeTheIdOnce(): void
    {
        $done = false;
        Event::listen('eloquent.creating: '.Project::class, function (Project $project) use (&$done): void {
            if ($done) {
                return;
            }
            $done = true;
            DB::table('projects')->insert(['workspace_id' => $project->workspace_id, 'key' => $project->key, 'name' => 'Created a moment earlier', 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    /**
     * @return array{Workspace, User}
     */
    private function manager(): array
    {
        $workspace = Workspace::factory()->create();

        return [$workspace, User::factory()->inWorkspace($workspace, 'manager')->create()];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{name: string, key: string|null, description: string|null, color: string, team_id: null, client_id: null, privacy: string, layout: string, portal_enabled: bool, due_date: null}
     */
    private function details(array $overrides = []): array
    {
        return [...['name' => 'Launch', 'key' => null, 'description' => null, 'color' => Project::COLORS[0], 'team_id' => null, 'client_id' => null, 'privacy' => 'workspace', 'layout' => 'list', 'portal_enabled' => false, 'due_date' => null], ...$overrides];
    }
}
