<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\Docs;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\DocRevision;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Project docs: writing, autosave conflicts, history, sharing and order. */
class DocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_from_an_unknown_template_starts_a_blank_doc(): void
    {
        [$member, $project] = $this->member();
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('create', 'no-such-template');

        $doc = ProjectDoc::sole();
        $this->assertSame('Untitled doc', $doc->title);
        $this->assertSame($member->id, $doc->created_by);
    }

    public function test_creating_from_a_template_uses_its_title(): void
    {
        [$member, $project] = $this->member();
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('create', 'meeting');

        $this->assertSame('Meeting notes', ProjectDoc::sole()->title);
    }

    public function test_duplicating_adds_a_copy_at_the_end(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->create(['title' => 'Spec', 'content' => '<p>Body</p>']);
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('duplicate');

        $copy = ProjectDoc::whereKeyNot($doc->id)->sole();
        $this->assertSame('Spec (copy)', $copy->title);
        $this->assertSame('<p>Body</p>', $copy->content);
        $this->assertSame($doc->position + 1, $copy->position);
    }

    public function test_autosave_stores_sanitized_html(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->create();
        $this->actingAs($member);

        $page = Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('startEdit')
            ->call('autosave', '<p>Hello</p><script>alert(1)</script>', 'New title');

        $page->assertReturned(fn (array $result): bool => $result['ok'] === true);
        $doc->refresh();
        $this->assertSame('New title', $doc->title);
        $this->assertStringContainsString('Hello', $doc->content);
        $this->assertStringNotContainsString('<script', $doc->content);
    }

    public function test_autosave_reports_a_conflict_when_someone_else_saved_since_the_editor_opened(): void
    {
        [$member, $project] = $this->member();
        $colleague = User::factory()->inWorkspace($project->workspace)->create(['name' => 'Rita Moss']);
        $doc = ProjectDoc::factory()->for($project)->create(['content' => '<p>Original</p>']);
        $this->actingAs($member);
        $page = Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('startEdit');
        $doc->forceFill(['content' => '<p>Rita’s words</p>', 'updated_by' => $colleague->id, 'version' => $doc->version + 1])->save();

        $page->call('autosave', '<p>Mine</p>', $doc->title);

        $page->assertReturned(['ok' => false, 'conflict' => true, 'by' => 'Rita Moss']);
        $this->assertSame('<p>Rita’s words</p>', $doc->fresh()->content);
    }

    public function test_autosave_reports_a_conflict_when_the_same_person_saved_from_another_tab(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->create(['content' => '<p>Original</p>']);
        $this->actingAs($member);
        $firstTab = Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('startEdit');
        $secondTab = Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('startEdit');
        $secondTab->call('autosave', '<p>From the second tab</p>', $doc->title);

        $firstTab->call('autosave', '<p>From the first tab</p>', $doc->title);

        $firstTab->assertReturned(fn (array $result): bool => $result['ok'] === false && $result['conflict'] === true);
        $this->assertSame('<p>From the second tab</p>', $doc->fresh()->content);
    }

    public function test_forcing_the_save_overwrites_the_other_version(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->create();
        $this->actingAs($member);
        $page = Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('startEdit');
        $doc->forceFill(['content' => '<p>Theirs</p>', 'version' => $doc->version + 1])->save();

        $page->call('autosave', '<p>Mine</p>', $doc->title, true);

        $page->assertReturned(fn (array $result): bool => $result['ok'] === true);
        $this->assertSame('<p>Mine</p>', $doc->fresh()->content);
    }

    public function test_the_first_save_of_an_edit_session_keeps_the_previous_version_in_history(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->create(['title' => 'Plan', 'content' => '<p>v1</p>']);
        $this->actingAs($member);
        $page = Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('startEdit');

        $page->call('autosave', '<p>v2</p>', 'Plan')->call('autosave', '<p>v3</p>', 'Plan');

        $this->assertSame(['<p>v1</p>'], DocRevision::where('doc_id', $doc->id)->pluck('content')->all());
        $this->assertSame('<p>v3</p>', $doc->fresh()->content);
    }

    public function test_restoring_a_revision_keeps_the_current_version_in_history(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->create(['title' => 'Plan', 'content' => '<p>Now</p>']);
        $old = DocRevision::factory()->for($doc, 'doc')->create(['title' => 'Plan', 'content' => '<p>Before</p>']);
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('restoreRevision', $old->id);

        $this->assertSame('<p>Before</p>', $doc->fresh()->content);
        $this->assertContains('<p>Now</p>', DocRevision::where('doc_id', $doc->id)->pluck('content')->all());
    }

    public function test_a_member_cannot_share_a_doc_with_the_client(): void
    {
        [$member, $project] = $this->member(portal: true);
        $doc = ProjectDoc::factory()->for($project)->create();
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('share', true)->assertForbidden();

        $this->assertFalse($doc->fresh()->client_visible);
    }

    public function test_a_manager_shares_a_doc_and_the_team_is_told(): void
    {
        [, $project] = $this->member(portal: true);
        $manager = User::factory()->inWorkspace($project->workspace, 'manager')->hasAttached($project)->create();
        $doc = ProjectDoc::factory()->for($project)->create();
        $this->actingAs($manager);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('share', true);

        $this->assertTrue($doc->fresh()->client_visible);
        $this->assertDatabaseHas('activities', ['project_id' => $project->id, 'kind' => 'doc', 'action' => 'shared', 'subject_id' => $doc->id]);
    }

    public function test_sharing_waits_until_the_client_portal_is_on(): void
    {
        [, $project] = $this->member();
        $admin = User::factory()->inWorkspace($project->workspace, 'admin')->create();
        $doc = ProjectDoc::factory()->for($project)->create();
        $this->actingAs($admin);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('share', true)
            ->assertDispatched('toast', message: 'Turn on the client portal for this project first (project details).');

        $this->assertFalse($doc->fresh()->client_visible);
    }

    public function test_moving_a_doc_down_swaps_it_with_the_next_one(): void
    {
        [$member, $project] = $this->member();
        $first = ProjectDoc::factory()->for($project)->create();
        $second = ProjectDoc::factory()->for($project)->create();
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $first->id)->call('move', 1);

        $this->assertSame([$second->id, $first->id], $project->docs()->pluck('id')->all());
    }

    public function test_the_author_can_delete_their_doc(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->writtenBy($member)->create();
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('delete');

        $this->assertModelMissing($doc);
    }

    public function test_another_member_cannot_delete_someone_elses_doc(): void
    {
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->writtenBy(User::factory()->inWorkspace($project->workspace)->create())->create();
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('delete')->assertForbidden();

        $this->assertModelExists($doc);
    }

    public function test_the_project_owner_can_delete_any_doc(): void
    {
        [$member, $project] = $this->member();
        $project->update(['owner_id' => $member->id]);
        $doc = ProjectDoc::factory()->for($project)->writtenBy(User::factory()->inWorkspace($project->workspace)->create())->create();
        $this->actingAs($member);

        Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)->call('delete');

        $this->assertModelMissing($doc);
    }

    public function test_an_image_dropped_into_the_editor_is_stored_on_the_doc(): void
    {
        Storage::fake('local');
        [$member, $project] = $this->member();
        $doc = ProjectDoc::factory()->for($project)->create();
        $this->actingAs($member);

        $page = Livewire::test(Docs::class, ['project' => $project])->call('select', $doc->id)
            ->set('docUpload', UploadedFile::fake()->image('diagram.png'))
            ->call('storeDocUpload');

        $attachment = Attachment::sole();
        $this->assertSame($doc->id, $attachment->doc_id);
        $page->assertReturned(fn (array $result): bool => $result['isImage'] === true);
        Storage::disk('local')->assertExists($attachment->path);
    }

    /**
     * @return array{User, Project}
     */
    private function member(bool $portal = false): array
    {
        $workspace = Workspace::factory()->create();
        $project = $portal
            ? Project::factory()->withPortal(Client::factory()->for($workspace)->create())->create()
            : Project::factory()->for($workspace)->create();

        return [User::factory()->inWorkspace($workspace)->create(), $project];
    }
}
