<?php

namespace Tests\Feature\Actions\Docs;

use App\Actions\Docs\RestoreDocRevision;
use App\Models\DocRevision;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Putting an earlier version of a doc back. */
class RestoreDocRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_the_version_and_keeps_the_replaced_one_in_the_history(): void
    {
        $doc = ProjectDoc::factory()->create(['title' => 'Now', 'content' => '<p>Current</p>']);
        $old = DocRevision::factory()->for($doc, 'doc')->create(['title' => 'Then', 'content' => '<p>Earlier</p>']);

        app(RestoreDocRevision::class)->handle($doc, $old, User::factory()->create());

        $doc->refresh();
        $this->assertSame(['Then', '<p>Earlier</p>', 1], [$doc->title, $doc->content, $doc->version]);
        $this->assertTrue(DocRevision::where('doc_id', $doc->id)->where('content', '<p>Current</p>')->exists());
    }
}
