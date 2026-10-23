<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\StatusUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusUpdate>
 */
class StatusUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'status' => 'on_track',
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
        ];
    }
}
