<?php

namespace Tests\Feature\Actions\Sections;

use App\Actions\Sections\DeleteSection;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Deleting a section moves its tasks to another one, and Undo puts everything back. */
class DeleteSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_move_to_the_end_of_the_fallback_section_in_their_order(): void
    {
        $project = Project::factory()->create();
        $fallback = Section::factory()->for($project)->create();
        $section = Section::factory()->for($project)->create();
        $existing = Task::factory()->inSection($fallback)->create();
        $first = Task::factory()->inSection($section)->create();
        $second = Task::factory()->inSection($section)->create();

        $result = app(DeleteSection::class)->handle($section, $fallback, User::factory()->create());

        $this->assertSame(2, $result['moved']);
        $this->assertModelMissing($section);
        $this->assertSame([$existing->id, $first->id, $second->id], Task::where('section_id', $fallback->id)->orderBy('position')->pluck('id')->all());
    }

    public function test_undo_brings_the_section_back_with_its_tasks_in_place(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $project = Project::factory()->for($workspace)->create();
        $fallback = Section::factory()->for($project)->create();
        $section = Section::factory()->for($project)->create();
        $first = Task::factory()->inSection($section)->create();
        $second = Task::factory()->inSection($section)->create();
        $result = app(DeleteSection::class)->handle($section, $fallback, $user);

        $this->actingAs($user)->postJson(route('undo', $result['undo']))->assertOk();

        $this->assertModelExists($section);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'section_id' => $section->id, 'position' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $second->id, 'section_id' => $section->id, 'position' => 2]);
    }

    public function test_the_last_empty_section_can_be_deleted_without_a_fallback(): void
    {
        $section = Section::factory()->create();

        $result = app(DeleteSection::class)->handle($section, null, User::factory()->create());

        $this->assertSame(0, $result['moved']);
        $this->assertModelMissing($section);
    }
}
