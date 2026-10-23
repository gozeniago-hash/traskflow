<?php

namespace Tests\Feature\Livewire\Notifications;

use App\Livewire\Notifications\Index;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

/** The notifications page and its filters. */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_project_and_by_kind(): void
    {
        $workspace = Workspace::factory()->create();
        $colleague = User::factory()->inWorkspace($workspace)->create();
        $website = Project::factory()->for($workspace)->create();
        $app = Project::factory()->for($workspace)->create();
        Activity::factory()->for(Task::factory()->for($website))->for($colleague)->create();
        Activity::factory()->for(Task::factory()->for($app))->for($colleague)->create();
        Activity::create(['project_id' => $website->id, 'user_id' => $colleague->id, 'kind' => 'section', 'action' => 'created', 'meta' => ['title' => 'QA']]);
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        $page = Livewire::test(Index::class)->set('project', (string) $website->id);
        $this->assertSame(2, $this->entries($page->viewData('groups')));

        $page->set('kind', 'section');
        $this->assertSame(1, $this->entries($page->viewData('groups')));
    }

    public function test_opening_the_page_marks_everything_read(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $this->actingAs($user);

        Livewire::test(Index::class);

        $this->assertNotNull($user->fresh()->notifications_read_at);
    }

    /**
     * @param Collection<string, Collection<int, Activity>> $groups
     */
    private function entries(Collection $groups): int
    {
        return $groups->flatten()->count();
    }
}
