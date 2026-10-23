<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invitation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending member invitation; the token and expiry are set when it is created.
 *
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'role' => 'member',
        ];
    }

    public function forRole(string $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    /** Invites a contact of a client into the portal. */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => ['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'role' => 'client']);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['accepted_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }
}
