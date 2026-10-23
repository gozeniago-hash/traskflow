<?php

namespace Tests\Feature\Actions\Docs;

use App\Actions\Docs\CreateDoc;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Adding a doc to a project. */
class CreateDocTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_the_doc_at_the_end_of_the_list_by_its_author(): void
    {
        $project = Project::factory()->create();
        ProjectDoc::factory()->for($project)->create(['position' => 4]);
        $author = User::factory()->create();

        $doc = app(CreateDoc::class)->handle($project, $author, 'Launch plan', '<p>Steps</p>');

        $this->assertSame([5, $author->id, $author->id], [$doc->position, $doc->created_by, $doc->updated_by]);
    }
}
