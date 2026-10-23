<?php

namespace Tests\Feature\Actions\Docs;

use App\Actions\Docs\SaveDoc;
use App\Models\DocRevision;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Autosaving a doc, and noticing that someone else saved meanwhile. */
class SaveDocTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_cleaned_html_and_counts_up_the_version(): void
    {
        $doc = ProjectDoc::factory()->create(['content' => '<p>Old</p>']);
        $editor = User::factory()->create();

        $result = app(SaveDoc::class)->handle($doc, $editor, '<p>New</p><script>alert(1)</script>', 'Spec', $doc->version);

        $this->assertSame(['saved' => true, 'conflict' => null, 'version' => 1], $result);
        $doc->refresh();
        $this->assertSame('<p>New</p>', $doc->content);
        $this->assertSame($editor->id, $doc->updated_by);
    }

    public function test_refuses_to_overwrite_a_save_made_after_the_editor_opened_even_in_the_same_second_or_by_the_same_person(): void
    {
        $this->freezeTime();
        $doc = ProjectDoc::factory()->create();
        $editor = User::factory()->create(['name' => 'Ana']);
        $opened = $doc->version;
        app(SaveDoc::class)->handle($doc, $editor, '<p>From my other tab</p>', $doc->title, $opened);

        $result = app(SaveDoc::class)->handle(ProjectDoc::find($doc->id), $editor, '<p>From this tab</p>', $doc->title, $opened);

        $this->assertSame(['saved' => false, 'conflict' => 'Ana', 'version' => 1], $result);
        $this->assertSame('<p>From my other tab</p>', $doc->fresh()->content);
    }

    public function test_saving_anyway_overwrites_the_other_save(): void
    {
        $doc = ProjectDoc::factory()->create();
        $editor = User::factory()->create();
        app(SaveDoc::class)->handle($doc, $editor, '<p>Theirs</p>', $doc->title, 0);

        $result = app(SaveDoc::class)->handle($doc, $editor, '<p>Mine</p>', $doc->title, 0, force: true);

        $this->assertTrue($result['saved']);
        $this->assertSame('<p>Mine</p>', $doc->fresh()->content);
    }

    public function test_the_first_save_of_a_session_keeps_the_previous_version_in_the_history(): void
    {
        $doc = ProjectDoc::factory()->create(['title' => 'Spec', 'content' => '<p>First draft</p>']);

        app(SaveDoc::class)->handle($doc, User::factory()->create(), '<p>Second draft</p>', 'Spec', 0, keepPrevious: true);

        $this->assertSame(['<p>First draft</p>'], DocRevision::where('doc_id', $doc->id)->pluck('content')->all());
    }

    public function test_nothing_is_saved_when_nothing_changed(): void
    {
        $doc = ProjectDoc::factory()->create(['title' => 'Spec', 'content' => '<p>Same</p>']);

        $result = app(SaveDoc::class)->handle($doc, User::factory()->create(), '<p>Same</p>', 'Spec', 0);

        $this->assertSame(['saved' => false, 'conflict' => null, 'version' => 0], $result);
    }
}
