<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Projects are open to the whole workspace unless a state narrows them.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'color' => fake()->randomElement(Project::COLORS),
            'privacy' => 'workspace',
            'status' => 'on_track',
        ];
    }

    /** Only the owner, project members and admins can open it. */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => ['privacy' => 'private']);
    }

    /** Shared with one team of the same workspace. */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => ['workspace_id' => $team->workspace_id, 'team_id' => $team->id, 'privacy' => 'team']);
    }

    /** Owned by someone, who can then always open it. */
    public function ownedBy(User $owner): static
    {
        return $this->state(fn (array $attributes) => ['owner_id' => $owner->id]);
    }

    /** Done for a client, who sees its shared parts in the portal. */
    public function withPortal(Client $client): static
    {
        return $this->state(fn (array $attributes) => ['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'portal_enabled' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['archived_at' => now()]);
    }
}
