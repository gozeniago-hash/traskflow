<?php

namespace Tests\Feature\Actions\GitHub;

use App\Actions\GitHub\StorePullRequest;
use App\Models\GithubRepository;
use App\Models\Project;
use App\Models\PullRequest;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Storing a pull request and linking it to the tickets it mentions. */
class StorePullRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_mentioned_tickets_and_drops_links_that_are_no_longer_mentioned(): void
    {
        $repository = GithubRepository::factory()->create();
        $project = Project::factory()->for($repository->workspace)->create(['key' => 'WEB']);
        $first = Task::factory()->for($project)->create();
        $second = Task::factory()->for($project)->create();
        $store = app(StorePullRequest::class);
        $store->handle($repository, ['number' => 3, 'title' => 'WEB-'.$first->number, 'state' => 'open', 'updated_at' => '2026-10-20T10:00:00Z']);

        $pr = $store->handle($repository, ['number' => 3, 'title' => 'WEB-'.$second->number, 'state' => 'open', 'updated_at' => '2026-10-20T11:00:00Z']);

        $this->assertSame([$second->id], $pr->tasks()->pluck('tasks.id')->all());
    }

    public function test_tickets_of_another_workspace_with_the_same_key_are_not_linked(): void
    {
        $repository = GithubRepository::factory()->create();
        $elsewhere = Task::factory()->for(Project::factory()->create(['key' => 'WEB']))->create();

        $pr = app(StorePullRequest::class)->handle($repository, ['number' => 3, 'title' => 'WEB-'.$elsewhere->number, 'state' => 'open']);

        $this->assertSame(0, $pr->tasks()->count());
        $this->assertSame(1, PullRequest::count());
    }
}
