<?php

namespace Database\Factories;

use App\Models\DocRevision;
use App\Models\ProjectDoc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocRevision>
 */
class DocRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doc_id' => ProjectDoc::factory(),
            'title' => fake()->sentence(3),
            'content' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
