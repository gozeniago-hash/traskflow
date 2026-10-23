<?php

namespace Tests\Feature\Policies;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Who can do what with a team project that is shared with its client in the portal. */
class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITIES = ['view', 'contribute', 'update', 'manageSections', 'manageContent', 'viewTeamData', 'shareWithClient', 'delete'];

    /**
     * Each person with the abilities they have; every other ability is refused.
     *
     * @return array<string, array{string, array<int, string>}>
     */
    public static function people(): array
    {
        $worker = ['view', 'contribute'];

        return [
            'workspace owner' => ['owner', self::ABILITIES],
            'admin' => ['admin', self::ABILITIES],
            'manager in the team' => ['manager in team', array_diff(self::ABILITIES, ['delete'])],
            'manager in the team who owns the project' => ['manager owning project', self::ABILITIES],
            'manager outside the team' => ['manager outside team', []],
            'member in the team' => ['member in team', $worker],
            'member who owns the project' => ['member owning project', [...$worker, 'manageSections', 'manageContent']],
            'member outside the team' => ['member outside team', []],
            'contact of the project client' => ['client of project', ['view']],
            'contact of another client' => ['client of another', []],
            'person from another workspace' => ['outsider', []],
        ];
    }

    /**
     * @param array<int, string> $allowed
     */
    #[DataProvider('people')]
    public function test_each_person_gets_exactly_the_project_abilities_of_their_role(string $who, array $allowed): void
    {
        [$user, $project] = $this->scene($who);

        $actual = [];
        foreach (self::ABILITIES as $ability) {
            $actual[$ability] = $user->can($ability, $project);
        }

        $expected = [];
        foreach (self::ABILITIES as $ability) {
            $expected[$ability] = in_array($ability, $allowed, true);
        }
        $this->assertSame($expected, $actual);
    }

    public function test_person_removed_from_the_workspace_can_no_longer_open_its_workspace_wide_projects(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $removed = User::factory()->create(['current_workspace_id' => $workspace->id]);

        $this->assertFalse($removed->can('view', $project));
        $this->assertFalse($removed->can('contribute', $project));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function creators(): array
    {
        return [
            'workspace owner' => ['owner', true],
            'admin' => ['admin', true],
            'manager' => ['manager', true],
            'member' => ['member', false],
            'client contact' => ['client', false],
        ];
    }

    #[DataProvider('creators')]
    public function test_only_admins_and_managers_can_create_projects(string $role, bool $expected): void
    {
        $workspace = Workspace::factory()->create();
        $client = $role === 'client' ? Client::factory()->for($workspace)->create() : null;
        $user = User::factory()->inWorkspace($workspace, $role, $client)->create();

        $this->assertSame($expected, $user->can('create', Project::class));
    }

    /**
     * Builds the project and the person the case is about.
     *
     * @return array{User, Project}
     */
    private function scene(string $who): array
    {
        $workspace = Workspace::factory()->create();
        $team = Team::factory()->for($workspace)->create();
        $client = Client::factory()->for($workspace)->create();
        $project = Project::factory()->forTeam($team)->withPortal($client)->create();

        $user = match ($who) {
            'owner', 'admin' => User::factory()->inWorkspace($workspace, $who)->create(),
            'manager in team', 'manager owning project' => User::factory()->inWorkspace($workspace, 'manager')->hasAttached($team)->create(),
            'manager outside team' => User::factory()->inWorkspace($workspace, 'manager')->create(),
            'member in team' => User::factory()->inWorkspace($workspace)->hasAttached($team)->create(),
            'member owning project', 'member outside team' => User::factory()->inWorkspace($workspace)->create(),
            'client of project' => User::factory()->inWorkspace($workspace, 'client', $client)->create(),
            'client of another' => User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create(),
            'outsider' => User::factory()->inWorkspace(Workspace::factory()->create(), 'admin')->create(),
            default => throw new InvalidArgumentException('Unknown person: '.$who),
        };

        if (str_ends_with($who, 'owning project')) {
            $project->update(['owner_id' => $user->id]);
        }

        return [$user, $project->fresh()];
    }
}
