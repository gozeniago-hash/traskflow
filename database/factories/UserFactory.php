<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /** The authenticator secret of users made withTwoFactor(), the RFC 6238 test key. */
    public const TWO_FACTOR_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /** @var array<int, string> */
    public const RECOVERY_CODES = ['aaaaa-aaaaa', 'bbbbb-bbbbb'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Belongs to a workspace with a role, and has it open.
     * A client contact also gets the client it speaks for.
     */
    public function inWorkspace(Workspace $workspace, string $role = 'member', ?Client $client = null): static
    {
        return $this->state(fn (array $attributes) => ['current_workspace_id' => $workspace->id])
            ->afterCreating(fn (User $user) => $workspace->users()->attach($user->id, ['role' => $role, 'client_id' => $client?->id]));
    }

    /** Has two-step verification turned on, with TWO_FACTOR_SECRET and RECOVERY_CODES. */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => self::TWO_FACTOR_SECRET,
            'two_factor_recovery_codes' => self::RECOVERY_CODES,
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
