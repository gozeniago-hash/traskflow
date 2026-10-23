<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\AuditLog;
use App\Models\AuditLog as Entry;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The audit log page: who did what in the workspace. */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function refusedRoles(): array
    {
        return ['manager' => ['manager'], 'member' => ['member']];
    }

    #[DataProvider('refusedRoles')]
    public function test_people_who_are_not_admins_cannot_read_the_audit_log(string $role): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs(User::factory()->inWorkspace($workspace, $role)->create())->get(route('audit-log'));

        $response->assertForbidden();
    }

    public function test_client_contacts_are_sent_to_their_portal(): void
    {
        $workspace = Workspace::factory()->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create();

        $response = $this->actingAs($contact)->get(route('audit-log'));

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_shows_this_workspaces_entries_and_its_peoples_sign_ins_only(): void
    {
        [$admin, $workspace] = $this->admin();
        Entry::factory()->create(['workspace_id' => $workspace->id, 'action' => 'team.created', 'description' => 'Created team Design']);
        Entry::factory()->create(['workspace_id' => null, 'user_id' => $admin->id, 'action' => 'auth.login', 'description' => 'Admin signed in']);
        $stranger = User::factory()->create();
        Entry::factory()->create(['workspace_id' => null, 'user_id' => $stranger->id, 'action' => 'auth.login', 'description' => 'Stranger signed in']);
        Entry::factory()->create(['action' => 'team.created', 'description' => 'Created team Elsewhere']);

        Livewire::actingAs($admin)->test(AuditLog::class)
            ->assertSee('Created team Design')
            ->assertSee('Admin signed in')
            ->assertDontSee('Stranger signed in')
            ->assertDontSee('Created team Elsewhere');
    }

    public function test_entries_can_be_filtered_by_kind(): void
    {
        [$admin, $workspace] = $this->admin();
        Entry::factory()->create(['workspace_id' => $workspace->id, 'action' => 'team.created', 'description' => 'Created team Design']);
        Entry::factory()->create(['workspace_id' => $workspace->id, 'action' => 'client.created', 'description' => 'Added client Brightside']);

        Livewire::actingAs($admin)->test(AuditLog::class)->set('group', 'team')
            ->assertSee('Created team Design')
            ->assertDontSee('Added client Brightside');
    }

    public function test_more_shows_the_next_fifty_entries(): void
    {
        [$admin, $workspace] = $this->admin();
        Entry::factory()->count(51)->create(['workspace_id' => $workspace->id, 'action' => 'team.updated']);
        $page = Livewire::actingAs($admin)->test(AuditLog::class);

        $page->assertViewHas('hasMore', true);
        $page->call('more');

        $page->assertSet('perPage', 100)->assertViewHas('hasMore', false);
        $this->assertSame(51, $page->viewData('entries')->count());
    }

    public function test_descriptions_are_shown_as_text_not_html(): void
    {
        [$admin, $workspace] = $this->admin();
        Entry::factory()->create(['workspace_id' => $workspace->id, 'action' => 'client.created', 'description' => 'Added client <script>alert(1)</script>']);

        Livewire::actingAs($admin)->test(AuditLog::class)
            ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
            ->assertDontSeeHtml('<script>alert(1)</script>');
    }

    public function test_export_downloads_the_entries_and_is_itself_logged(): void
    {
        [$admin, $workspace] = $this->admin();
        Entry::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id, 'action' => 'team.created', 'description' => 'Created team Design']);

        $response = Livewire::actingAs($admin)->test(AuditLog::class)->call('export');

        $response->assertFileDownloaded('audit-log-'.now()->format('Y-m-d').'.csv');
        $csv = base64_decode($response->__get('effects')['download']['content']);
        $this->assertStringContainsString('team.created', $csv);
        $this->assertStringContainsString('Created team Design', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.audit_exported', 'workspace_id' => $workspace->id]);
    }

    /**
     * @return array{User, Workspace}
     */
    private function admin(): array
    {
        $workspace = Workspace::factory()->create();

        return [User::factory()->inWorkspace($workspace, 'admin')->create(), $workspace];
    }
}
