<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Settings;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\GithubRepository;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The workspace settings page: name, two-step verification, archived projects, email and GitHub. */
class SettingsTest extends TestCase
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
    public function test_people_who_are_not_admins_cannot_open_settings(string $role): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs(User::factory()->inWorkspace($workspace, $role)->create())->get(route('settings'));

        $response->assertForbidden();
    }

    public function test_client_contacts_are_sent_to_their_portal(): void
    {
        $workspace = Workspace::factory()->create();
        $contact = User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create();

        $response = $this->actingAs($contact)->get(route('settings'));

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_renaming_the_workspace_is_logged(): void
    {
        [$admin, $workspace] = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)->set('name', 'Acme Studio')->call('save')->assertHasNoErrors();

        $this->assertSame('Acme Studio', $workspace->fresh()->name);
        $this->assertSame(['workspace.renamed'], AuditLog::pluck('action')->all());
    }

    public function test_saving_the_same_name_logs_nothing(): void
    {
        [$admin] = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)->call('save')->assertHasNoErrors();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_the_workspace_needs_a_name_of_at_most_120_characters(): void
    {
        [$admin, $workspace] = $this->admin();
        $page = Livewire::actingAs($admin)->test(Settings::class);

        $page->set('name', '')->call('save')->assertHasErrors(['name' => 'required']);
        $page->set('name', str_repeat('a', 121))->call('save')->assertHasErrors(['name' => 'max']);

        $this->assertSame($workspace->name, $workspace->fresh()->name);
    }

    public function test_an_admin_without_two_step_verification_cannot_require_it(): void
    {
        [$admin, $workspace] = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->call('setRequireTwoFactor', true)
            ->assertDispatched('toast', fn (string $name, array $params) => $params['type'] === 'error'
                && $params['message'] === 'Turn on two-step verification for your own account first (Profile), so you don’t lock yourself out.');

        $this->assertFalse($workspace->fresh()->require_two_factor);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_an_admin_with_two_step_verification_can_require_it_for_everyone(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->withTwoFactor()->create();

        Livewire::actingAs($admin)->test(Settings::class)->call('setRequireTwoFactor', true);

        $this->assertTrue($workspace->fresh()->require_two_factor);
        $this->assertSame(['workspace.two_factor_required'], AuditLog::pluck('action')->all());
    }

    public function test_an_archived_project_can_be_restored(): void
    {
        [$admin, $workspace] = $this->admin();
        $project = Project::factory()->for($workspace)->archived()->create();

        Livewire::actingAs($admin)->test(Settings::class)->call('unarchive', $project->id);

        $this->assertNull($project->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.restored', 'subject_id' => $project->id]);
    }

    public function test_a_project_of_another_workspace_cannot_be_restored(): void
    {
        [$admin] = $this->admin();
        $elsewhere = Project::factory()->archived()->create();

        Livewire::actingAs($admin)->test(Settings::class)->call('unarchive', $elsewhere->id)->assertNotFound();

        $this->assertNotNull($elsewhere->fresh()->archived_at);
    }

    public function test_test_emails_are_limited_to_three_per_ten_minutes(): void
    {
        [$admin] = $this->admin();
        $page = Livewire::actingAs($admin)->test(Settings::class);

        $page->call('sendTestEmail')->call('sendTestEmail')->call('sendTestEmail')->call('sendTestEmail');

        $page->assertSet('mailTest', ['ok' => false, 'message' => 'Wait a few minutes before sending another test email.']);
    }

    public function test_connecting_github_stores_the_token_and_login(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.github.com/user' => Http::response(['login' => 'octocat'])]);
        [$admin, $workspace] = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->set('githubToken', ' ghp_secret ')->call('connectGitHub')
            ->assertHasNoErrors()->assertSet('githubToken', '');

        $workspace->refresh();
        $this->assertSame('ghp_secret', $workspace->github_token);
        $this->assertSame('octocat', $workspace->github_login);
        $this->assertSame(['github.connected'], AuditLog::pluck('action')->all());
    }

    public function test_a_token_github_rejects_is_explained_and_not_stored(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401)]);
        [$admin, $workspace] = $this->admin();

        $page = Livewire::actingAs($admin)->test(Settings::class)->set('githubToken', 'ghp_wrong')->call('connectGitHub');

        $this->assertSame(['GitHub rejected that token. Check it was copied in full and has not expired.'], $page->errors()->get('githubToken'));
        $this->assertNull($workspace->fresh()->github_token);
    }

    public function test_disconnecting_github_forgets_the_token(): void
    {
        $workspace = Workspace::factory()->connectedToGitHub()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();

        Livewire::actingAs($admin)->test(Settings::class)->call('disconnectGitHub');

        $workspace->refresh();
        $this->assertNull($workspace->github_token);
        $this->assertNull($workspace->github_login);
        $this->assertSame(['github.disconnected'], AuditLog::pluck('action')->all());
    }

    public function test_adding_a_repository_installs_its_webhook_and_reads_its_pull_requests(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/acme/site' => Http::response(['full_name' => 'acme/site']),
            'https://api.github.com/repos/acme/site/hooks' => Http::response(['id' => 991], 201),
            'https://api.github.com/repos/acme/site/pulls*' => Http::response([]),
        ]);
        $workspace = Workspace::factory()->connectedToGitHub()->create();
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();

        Livewire::actingAs($admin)->test(Settings::class)
            ->set('newRepo', 'https://github.com/acme/site.git')->call('addRepository')
            ->assertHasNoErrors()->assertSet('newRepo', '')->assertSet('showWebhookFor', null);

        $repository = GithubRepository::sole();
        $this->assertSame('acme/site', $repository->full_name);
        $this->assertSame(991, $repository->webhook_id);
        $this->assertNotNull($repository->last_synced_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'github.repository_added', 'subject_id' => $repository->id]);
    }

    public function test_adding_a_repository_twice_is_refused(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.github.com/repos/acme/site' => Http::response(['full_name' => 'acme/site'])]);
        $workspace = Workspace::factory()->connectedToGitHub()->create();
        GithubRepository::factory()->for($workspace)->create(['full_name' => 'acme/site']);
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();

        $page = Livewire::actingAs($admin)->test(Settings::class)->set('newRepo', 'acme/site')->call('addRepository');

        $this->assertSame(['That repository is already added.'], $page->errors()->get('newRepo'));
        $this->assertSame(1, GithubRepository::count());
    }

    public function test_a_repository_cannot_be_added_before_github_is_connected(): void
    {
        Http::preventStrayRequests();
        [$admin] = $this->admin();

        $page = Livewire::actingAs($admin)->test(Settings::class)->set('newRepo', 'acme/site')->call('addRepository');

        $this->assertSame(['Connect GitHub first.'], $page->errors()->get('newRepo'));
        $this->assertSame(0, GithubRepository::count());
    }

    public function test_removing_a_repository_deletes_its_webhook_on_github(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.github.com/repos/acme/site/hooks/77' => Http::response(null, 204)]);
        $workspace = Workspace::factory()->connectedToGitHub()->create();
        $repository = GithubRepository::factory()->for($workspace)->create(['full_name' => 'acme/site', 'webhook_id' => 77]);
        $admin = User::factory()->inWorkspace($workspace, 'admin')->create();

        Livewire::actingAs($admin)->test(Settings::class)->call('removeRepository', $repository->id);

        $this->assertModelMissing($repository);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://api.github.com/repos/acme/site/hooks/77');
        $this->assertDatabaseHas('audit_logs', ['action' => 'github.repository_removed']);
    }

    public function test_webhook_help_cannot_be_opened_for_another_workspaces_repository(): void
    {
        [$admin] = $this->admin();
        $elsewhere = GithubRepository::factory()->create();

        $page = Livewire::actingAs($admin)->test(Settings::class);

        $page->call('toggleWebhookHelp', $elsewhere->id)->assertNotFound();

        $page->assertSet('showWebhookFor', null);
    }

    public function test_webhook_help_opens_and_closes_for_a_repository_of_the_workspace(): void
    {
        [$admin, $workspace] = $this->admin();
        $repository = GithubRepository::factory()->for($workspace)->create();
        $page = Livewire::actingAs($admin)->test(Settings::class);

        $page->call('toggleWebhookHelp', $repository->id)->assertSet('showWebhookFor', $repository->id);
        $page->call('toggleWebhookHelp', $repository->id)->assertSet('showWebhookFor', null);
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
