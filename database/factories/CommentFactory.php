<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Comments are internal (hidden from the client) unless a state shares them.
 *
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'is_internal' => true,
        ];
    }

    public function forClient(): static
    {
        return $this->state(fn (array $attributes) => ['is_internal' => false]);
    }
}
