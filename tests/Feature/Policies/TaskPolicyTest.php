<?php

namespace Tests\Feature\Policies;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Who can do what with a task that is shared with the client and waiting for their approval. */
class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITIES = ['view', 'update', 'delete', 'comment', 'approve'];

    /**
     * Each person with the abilities they have; every other ability is refused.
     *
     * @return array<string, array{string, array<int, string>}>
     */
    public static function people(): array
    {
        return [
            'admin' => ['admin', ['view', 'update', 'delete', 'comment']],
            'manager on the project' => ['manager on project', ['view', 'update', 'delete', 'comment']],
            'member who created the task' => ['creator', ['view', 'update', 'delete', 'comment']],
            'member who owns the project' => ['project owner', ['view', 'update', 'delete', 'comment']],
            'other member' => ['member', ['view', 'update', 'comment']],
            'contact of the project client' => ['client of project', ['view', 'comment', 'approve']],
            'contact of another client' => ['client of another', []],
            'person removed from the workspace' => ['removed', []],
            'person from another workspace' => ['outsider', []],
        ];
    }

    /**
     * @param array<int, string> $allowed
     */
    #[DataProvider('people')]
    public function test_each_person_gets_exactly_the_task_abilities_of_their_role(string $who, array $allowed): void
    {
        [$user, $task] = $this->scene($who);

        $actual = [];
        $expected = [];
        foreach (self::ABILITIES as $ability) {
            $actual[$ability] = $user->can($ability, $task);
            $expected[$ability] = in_array($ability, $allowed, true);
        }

        $this->assertSame($expected, $actual);
    }

    public function test_client_cannot_see_a_task_that_is_not_shared_with_them(): void
    {
        [$client, $task] = $this->scene('client of project');
        $task->update(['client_visible' => false]);

        $this->assertFalse($client->can('view', $task));
        $this->assertFalse($client->can('comment', $task));
    }

    public function test_client_can_approve_only_while_approval_is_pending(): void
    {
        [$client, $task] = $this->scene('client of project');
        $task->update(['approval_status' => 'approved']);

        $this->assertFalse($client->can('approve', $task));
    }

    public function test_task_without_a_creator_can_only_be_deleted_by_the_people_who_manage_the_project(): void
    {
        [$member, $task] = $this->scene('member');
        $task->update(['creator_id' => null]);
        $manager = User::factory()->inWorkspace($task->project->workspace, 'manager')->hasAttached($task->project)->create();

        $this->assertFalse($member->can('delete', $task));
        $this->assertTrue($manager->can('delete', $task));
    }

    /**
     * Builds a workspace-wide portal project, a shared task awaiting approval and the person the case is about.
     *
     * @return array{User, Task}
     */
    private function scene(string $who): array
    {
        $workspace = Workspace::factory()->create();
        $client = Client::factory()->for($workspace)->create();
        $project = Project::factory()->withPortal($client)->create();
        $creator = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for($project)->createdBy($creator)->awaitingApproval()->create();

        $user = match ($who) {
            'admin' => User::factory()->inWorkspace($workspace, 'admin')->create(),
            'manager on project' => User::factory()->inWorkspace($workspace, 'manager')->hasAttached($project)->create(),
            'creator' => $creator,
            'project owner', 'member' => User::factory()->inWorkspace($workspace)->create(),
            'client of project' => User::factory()->inWorkspace($workspace, 'client', $client)->create(),
            'client of another' => User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create(),
            'removed' => User::factory()->create(['current_workspace_id' => $workspace->id]),
            'outsider' => User::factory()->inWorkspace(Workspace::factory()->create(), 'admin')->create(),
            default => throw new InvalidArgumentException('Unknown person: '.$who),
        };

        if ($who === 'project owner') {
            $project->update(['owner_id' => $user->id]);
        }

        return [$user, $task->fresh()];
    }
}
