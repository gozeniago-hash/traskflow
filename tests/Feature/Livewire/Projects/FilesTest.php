<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\Files;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The project's Files tab: uploads and what the client sees. */
class FilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_members_upload_stays_internal_even_when_they_ask_to_share_it(): void
    {
        Storage::fake('local');
        [$workspace, $project] = $this->portalProject();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(Files::class, ['project' => $project])
            ->set('shareWithClient', true)
            ->set('uploads', [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')]);

        $this->assertFalse(Attachment::sole()->client_visible);
    }

    public function test_a_managers_upload_is_shared_when_they_ask(): void
    {
        Storage::fake('local');
        [$workspace, $project] = $this->portalProject();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'manager')->hasAttached($project)->create());

        Livewire::test(Files::class, ['project' => $project])
            ->set('shareWithClient', true)
            ->set('uploads', [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')]);

        $this->assertTrue(Attachment::sole()->client_visible);
    }

    public function test_sharing_sets_the_state_asked_for_even_when_clicked_twice(): void
    {
        [$workspace, $project] = $this->portalProject();
        $file = Attachment::factory()->for($project)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());
        $page = Livewire::test(Files::class, ['project' => $project]);

        $page->call('share', $file->id, true)->call('share', $file->id, true);

        $this->assertTrue($file->fresh()->client_visible);
    }

    public function test_a_member_cannot_change_what_the_client_sees(): void
    {
        [$workspace, $project] = $this->portalProject();
        $file = Attachment::factory()->for($project)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(Files::class, ['project' => $project])->call('share', $file->id, true)->assertForbidden();

        $this->assertFalse($file->fresh()->client_visible);
    }

    public function test_the_uploader_edits_a_files_details(): void
    {
        [$workspace, $project] = $this->portalProject();
        $member = User::factory()->inWorkspace($workspace)->create();
        $file = Attachment::factory()->for($project)->uploadedBy($member)->create();
        $this->actingAs($member);

        Livewire::test(Files::class, ['project' => $project])
            ->call('edit', $file->id)
            ->set('form.title', 'Signed contract')
            ->set('form.description', 'Final version')
            ->set('form.group', 'Contracts')
            ->call('addMetaRow')
            ->set('form.meta.0.label', 'Approved by')
            ->set('form.meta.0.value', 'Ana')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertSet('editingId', null)
            ->assertSee('Signed contract');

        $file->refresh();
        $this->assertSame(['Signed contract', 'Final version', 'Contracts', [['label' => 'Approved by', 'value' => 'Ana']]], [$file->title, $file->description, $file->group, $file->meta]);
    }

    public function test_a_member_cannot_edit_the_details_of_someone_elses_file(): void
    {
        [$workspace, $project] = $this->portalProject();
        $file = Attachment::factory()->for($project)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace)->create());

        Livewire::test(Files::class, ['project' => $project])->call('edit', $file->id)->assertForbidden();

        $this->assertNull($file->fresh()->title);
    }

    public function test_a_title_longer_than_150_characters_is_refused(): void
    {
        [$workspace, $project] = $this->portalProject();
        $file = Attachment::factory()->for($project)->create();
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());

        Livewire::test(Files::class, ['project' => $project])
            ->call('edit', $file->id)
            ->set('form.title', str_repeat('a', 151))
            ->call('saveDetails')
            ->assertHasErrors(['form.title' => 'max'])
            ->assertSee('The title field must not be greater than 150 characters.');

        $this->assertNull($file->fresh()->title);
    }

    public function test_choosing_a_group_shows_only_the_files_in_it(): void
    {
        [$workspace, $project] = $this->portalProject();
        Attachment::factory()->for($project)->create(['title' => 'Signed contract', 'group' => 'Contracts']);
        Attachment::factory()->for($project)->create(['title' => 'Homepage mockup', 'group' => 'Designs']);
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());

        Livewire::test(Files::class, ['project' => $project])
            ->set('group', 'Contracts')
            ->assertSee('Signed contract')
            ->assertDontSee('Homepage mockup');
    }

    public function test_file_details_are_escaped_on_the_page(): void
    {
        [$workspace, $project] = $this->portalProject();
        Attachment::factory()->for($project)->create(['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(2)>']);
        $this->actingAs(User::factory()->inWorkspace($workspace, 'admin')->create());

        Livewire::test(Files::class, ['project' => $project])
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false);
    }

    /**
     * @return array{Workspace, Project}
     */
    private function portalProject(): array
    {
        $workspace = Workspace::factory()->create();

        return [$workspace, Project::factory()->withPortal(Client::factory()->for($workspace)->create())->create()];
    }
}
