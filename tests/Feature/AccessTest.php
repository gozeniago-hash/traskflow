<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\ProjectDoc;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_registration_creates_a_workspace_owned_by_the_user(): void
    {
        $this->post('/register', [
            'name' => 'New Owner',
            'email' => 'owner@example.com',
            'workspace' => 'New Co',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('home'));

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertSame('New Co', $user->currentWorkspace->name);
        $this->assertTrue($user->isOwner());
    }

    public function test_members_see_the_internal_app(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $project = Project::where('name', 'Brightside Website Redesign')->first();

        $this->actingAs($owner)->get('/home')->assertOk()->assertSee('My tasks');
        $this->actingAs($owner)->get(route('projects.show', [$project, 'board']))->assertOk()->assertSee('Homepage wireframes');
        $this->actingAs($owner)->get(route('my-tasks'))->assertOk();
        $this->actingAs($owner)->get(route('projects.index'))->assertOk();
    }

    public function test_clients_are_redirected_to_the_portal(): void
    {
        $client = User::where('email', 'client@brightside.test')->first();

        $this->actingAs($client)->get('/home')->assertRedirect(route('portal.dashboard'));
        $this->actingAs($client)->get('/')->assertRedirect(route('portal.dashboard'));
    }

    public function test_clients_only_see_shared_tasks_of_their_projects(): void
    {
        $client = User::where('email', 'client@brightside.test')->first();
        $shared = Project::where('name', 'Brightside Website Redesign')->first();
        $internal = Project::where('name', 'Mobile App v2 Launch')->first();

        $this->actingAs($client)->get(route('portal.dashboard'))->assertOk()->assertSee('Brightside Website Redesign')->assertDontSee('Mobile App v2 Launch');

        $this->actingAs($client)->get(route('portal.projects.show', ['project' => $shared, 'tab' => 'tasks']))
            ->assertOk()
            ->assertSee('Homepage wireframes')          // shared
            ->assertDontSee('Design system tokens');     // internal-only task

        $this->actingAs($client)->get(route('portal.projects.show', $internal))->assertForbidden();
    }

    public function test_private_projects_are_hidden_from_non_members(): void
    {
        $project = Project::where('name', 'Mobile App v2 Launch')->first();
        $project->update(['privacy' => 'private']);
        $jo = User::where('email', 'jo@taskflow.test')->first(); // member, not on this project

        $this->actingAs($jo)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_task_policy_blocks_clients_from_editing(): void
    {
        $client = User::where('email', 'client@brightside.test')->first();
        $task = Task::where('title', 'Homepage wireframes')->first();

        $this->assertTrue($client->can('view', $task));
        $this->assertFalse($client->can('update', $task));
    }

    public function test_reports_render_for_members_and_are_blocked_for_clients(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        foreach (['incomplete', 'upcoming', 'projects', 'completed'] as $report) {
            $this->actingAs($owner)->get(route('reports', ['r' => $report]))->assertOk();
        }

        $client = User::where('email', 'client@brightside.test')->first();
        $this->actingAs($client)->get(route('reports'))->assertRedirect(route('portal.dashboard'));
    }

    public function test_clients_cannot_open_files_from_internal_comments(): void
    {
        Storage::fake('local');
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $client = User::where('email', 'client@brightside.test')->first();
        $task = Task::where('title', 'Visual design: homepage and pricing')->first(); // shared with client

        $internal = $task->comments()->create(['user_id' => $owner->id, 'body' => 'internal', 'is_internal' => true]);
        $public = $task->comments()->create(['user_id' => $owner->id, 'body' => 'public', 'is_internal' => false]);
        $hidden = Attachment::storeUpload(UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), $task->project, $owner, $task, false, $internal);
        $shared = Attachment::storeUpload(UploadedFile::fake()->create('mockup.pdf', 10, 'application/pdf'), $task->project, $owner, $task, false, $public);

        $this->actingAs($client)->get(route('attachments.download', $hidden))->assertForbidden();
        $this->actingAs($client)->get(route('attachments.download', $shared))->assertOk();
        $this->actingAs($owner)->get(route('attachments.download', [$hidden, 'inline' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_docs_are_sanitized_and_only_shared_docs_reach_the_portal(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $client = User::where('email', 'client@brightside.test')->first();
        $project = Project::where('name', 'Brightside Website Redesign')->first();

        $shared = $project->docs()->create(['title' => 'Shared spec', 'client_visible' => true, 'content' => '<div>Hello<script>alert(1)</script><img src="x" onerror="alert(1)"></div>']);
        $project->docs()->create(['title' => 'Secret internal notes', 'client_visible' => false, 'content' => '<div>internal</div>']);

        $this->assertStringNotContainsString('<script', $shared->fresh()->content);
        $this->assertStringNotContainsString('onerror', $shared->fresh()->content);

        $this->actingAs($owner)->get(route('projects.show', [$project, 'docs']))->assertOk()->assertSee('Shared spec');

        $this->actingAs($client)->get(route('portal.projects.show', ['project' => $project, 'tab' => 'docs', 'doc' => $shared->id]))
            ->assertOk()->assertSee('Shared spec')->assertDontSee('Secret internal notes');
    }

    public function test_projects_get_ids_and_tasks_get_ticket_numbers(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $web = Project::where('name', 'Brightside Website Redesign')->first();

        $this->assertSame('BWR', $web->key);
        $this->assertSame(range(1, $web->tasks()->count()), $web->tasks()->orderBy('number')->pluck('number')->all());

        $next = $web->tasks()->create(['title' => 'New one', 'section_id' => $web->sections()->first()->id]);
        $this->assertSame($web->tasks()->count(), $next->number);
        $this->assertSame('BWR-'.$next->number, $next->fresh('project')->ticket());

        // A second project with a similar name gets its own key.
        $other = $web->workspace->projects()->create(['name' => 'Big Web Rebuild']);
        $this->assertSame('BWR2', $other->key);
        $this->assertSame(1, $other->tasks()->create(['title' => 'First'])->number);

        // Ticket links resolve to the task.
        $this->actingAs($owner)->get('/t/bwr-'.$next->number)->assertRedirectContains('task='.$next->id);
        $this->actingAs($owner)->get('/t/NOPE-1')->assertNotFound();
    }

    public function test_custom_fields_validate_and_store_values(): void
    {
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $type = $web->fields()->where('name', 'Type')->first();
        $points = $web->fields()->where('name', 'Story points')->first();

        $this->assertNotNull($type);
        $bug = collect($type->optionList())->firstWhere('label', 'Bug');

        $this->assertSame($bug['id'], $type->normalize($bug['id']));
        $this->assertSame('Bug', $type->display($bug['id']));
        $this->assertSame('8', $points->normalize('8'));
        $this->assertNull($points->normalize(''));
        $this->assertContains('numeric', $points->rules());

        $multi = $web->fields()->create(['name' => 'Platforms', 'type' => 'multiselect', 'options' => ProjectField::makeOptions(['Web', 'iOS'])]);
        [$a, $b] = array_column($multi->optionList(), 'id');
        $this->assertSame(json_encode([$a]), $multi->normalize([$a, 'not-an-option']));
        $this->assertSame('Web, iOS', $multi->display(json_encode([$a, $b])));

        $owner = User::where('email', 'admin@taskflow.test')->first();
        $this->actingAs($owner)->get(route('projects.show', [$web, 'board']))->assertOk()->assertSee('Story points');
    }

    public function test_adding_a_dropdown_custom_field_saves_its_options(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $this->actingAs($owner);

        Livewire::test(\App\Livewire\Projects\Fields::class, ['project' => $web])
            ->call('create')
            ->set('form.name', 'Severity')
            ->set('form.type', 'select')
            ->set('newOption', 'High')->call('addOption')
            ->set('newOption', 'Low')->call('addOption')
            ->call('save')
            ->assertHasNoErrors();

        $field = $web->fields()->where('name', 'Severity')->firstOrFail();
        $this->assertSame(['High', 'Low'], array_column($field->optionList(), 'label'));
        $this->assertNotEmpty($field->optionList()[0]['id']);

        // Editing and renaming an option keeps its id.
        $id = $field->optionList()[0]['id'];
        Livewire::test(\App\Livewire\Projects\Fields::class, ['project' => $web])
            ->call('edit', $field->id)
            ->set('form.options.0.label', 'Critical')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(['id' => $id, 'label' => 'Critical'], array_intersect_key($field->fresh()->optionList()[0], ['id' => 1, 'label' => 1]));

        // Presets work too.
        Livewire::test(\App\Livewire\Projects\Fields::class, ['project' => $web])
            ->call('addPreset', 'effort')->call('save')->assertHasNoErrors();
        $this->assertTrue($web->fields()->where('name', 'Effort')->exists());
    }

    public function test_editing_the_project_refreshes_the_overview_on_the_same_page(): void
    {
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $this->actingAs($admin);
        $project = Project::where('workspace_id', $admin->current_workspace_id)->firstOrFail();

        $overview = Livewire::test(\App\Livewire\Projects\Overview::class, ['project' => $project]);

        Livewire::test(\App\Livewire\Projects\Show::class, ['project' => $project, 'view' => 'overview'])
            ->call('openEdit')
            ->set('form.start_date', '')
            ->set('form.due_date', '2026-11-30')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertDispatched('project-updated');

        $overview->dispatch('project-updated')->assertSee('Nov 30, 2026');
    }

    public function test_renaming_the_workspace_refreshes_the_sidebar(): void
    {
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Admin\Settings::class)
            ->set('name', 'Appyugo Inc')
            ->call('save')
            ->assertDispatched('workspace-updated');

        Livewire::test(\App\Livewire\Sidebar::class, ['route' => 'settings'])
            ->assertSee('Appyugo Inc');
    }

    public function test_github_sync_links_pull_requests_to_tickets(): void
    {
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $ws = $admin->currentWorkspace;
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $task = $web->tasks()->whereNull('parent_id')->orderBy('number')->first();
        $ticket = $task->ticket($web->key);

        $ws->forceFill(['github_token' => 'test-token', 'github_login' => 'octo'])->save();
        $repo = $ws->githubRepositories()->create(['full_name' => 'acme/site', 'webhook_secret' => 'shh']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/repos/acme/site/pulls*' => \Illuminate\Support\Facades\Http::response([
                ['number' => 7, 'title' => $ticket.' Hero section', 'html_url' => 'https://github.com/acme/site/pull/7', 'state' => 'open', 'draft' => false, 'merged_at' => null, 'head' => ['ref' => 'feature'], 'user' => ['login' => 'octo'], 'updated_at' => now()->toIso8601String(), 'body' => ''],
                ['number' => 8, 'title' => 'Unrelated', 'html_url' => 'https://github.com/acme/site/pull/8', 'state' => 'closed', 'merged_at' => now()->toIso8601String(), 'head' => ['ref' => 'chore'], 'user' => ['login' => 'octo'], 'updated_at' => now()->toIso8601String(), 'body' => null],
            ]),
        ]);

        $this->assertSame(2, \App\Support\GitHub::for($ws)->sync($repo));
        $this->assertSame([7], $task->pullRequests()->pluck('number')->all());

        // A webhook moves the link to another ticket via the branch name; bad signatures are refused.
        $other = $web->tasks()->whereNull('parent_id')->where('id', '!=', $task->id)->first();
        $payload = json_encode(['action' => 'edited', 'pull_request' => [
            'number' => 7, 'title' => 'Hero section', 'html_url' => 'https://github.com/acme/site/pull/7', 'state' => 'closed',
            'merged_at' => now()->toIso8601String(), 'head' => ['ref' => strtolower($other->ticket($web->key)).'-hero'], 'user' => ['login' => 'octo'], 'updated_at' => now()->toIso8601String(),
        ]]);
        $headers = ['X-GitHub-Event' => 'pull_request', 'Content-Type' => 'application/json'];

        $this->call('POST', route('github.webhook', $repo), [], [], [], $this->transformHeadersToServerVars($headers + ['X-Hub-Signature-256' => 'sha256=bad']), $payload)
            ->assertStatus(401);
        $this->call('POST', route('github.webhook', $repo), [], [], [], $this->transformHeadersToServerVars($headers + ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $payload, 'shh')]), $payload)
            ->assertOk();

        $this->assertSame([], $task->pullRequests()->pluck('number')->all());
        $this->assertSame('merged', $other->pullRequests()->first()->status());
    }

    public function test_key_resources_links_files_and_client_sharing(): void
    {
        Storage::fake('local');
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $client = User::where('email', 'client@brightside.test')->first();
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Projects\Overview::class, ['project' => $web])
            ->set('resourceUrl', 'figma.com/file/abc')
            ->call('addLink')
            ->assertHasNoErrors()
            ->set('resourceFiles', [UploadedFile::fake()->create('Brief.pdf', 20, 'application/pdf')])
            ->assertHasNoErrors();

        $link = $web->resources()->whereNotNull('url')->first();
        $file = $web->resources()->whereNotNull('attachment_id')->first();
        $this->assertSame('https://figma.com/file/abc', $link->url);
        $this->assertSame('Figma', $link->title);
        $this->assertSame('Brief', $file->title);

        // Shared with the client by default…
        $this->actingAs($client)->get($file->attachment->downloadUrl())->assertOk();
        Livewire::test(\App\Livewire\Portal\ProjectView::class, ['project' => $web])->assertSee('Brief')->assertSee('Figma');

        // …until marked internal.
        $this->actingAs($admin);
        Livewire::test(\App\Livewire\Projects\Overview::class, ['project' => $web])->call('toggleResourceShare', $file->id);
        $this->actingAs($client)->get($file->attachment->fresh()->downloadUrl())->assertForbidden();
        Livewire::test(\App\Livewire\Portal\ProjectView::class, ['project' => $web])->assertDontSee('Brief')->assertSee('Figma');
    }

    public function test_timesheets_show_and_export_recorded_time(): void
    {
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $client = User::where('email', 'client@brightside.test')->first();
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $task = $web->tasks()->whereNull('parent_id')->first();
        $this->actingAs($admin);

        $day = now(config('taskflow.timezone'))->startOfMonth()->addDays(2)->setTime(9, 0);
        \App\Models\TimeEntry::create([
            'project_id' => $web->id, 'task_id' => $task->id, 'user_id' => $admin->id,
            'started_at' => $day->copy()->utc(), 'ended_at' => $day->copy()->addMinutes(90)->utc(),
            'duration_seconds' => 5400, 'note' => 'Timesheet test entry', 'is_manual' => false,
        ]);

        $tab = Livewire::test(\App\Livewire\Projects\Timesheet::class, ['project' => $web])
            ->set('range', 'this_month')
            ->assertSee('Timesheet test entry');
        $tab->call('export', 'xlsx')->assertFileDownloaded();
        $tab->call('export', 'pdf')->assertFileDownloaded();

        $sheet = new \App\Support\Timesheet($admin, $day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString(), [$web->id]);
        [$pdf, $name, $mime] = $sheet->export('pdf', 'project', 'Test');
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertSame('application/pdf', $mime);
        [$xlsx] = $sheet->export('xlsx', 'people', 'Test');
        $this->assertStringStartsWith("PK\x03\x04", $xlsx);

        Livewire::test(\App\Livewire\Reports\Index::class)
            ->set('report', 'timesheet')->set('range', 'this_month')
            ->assertSee($admin->name)
            ->call('exportTimesheet', 'xlsx')->assertFileDownloaded();

        // Clients never reach the timesheet tab.
        $this->actingAs($client)->get(route('projects.show', [$web, 'timesheet']))->assertRedirect();
    }

    public function test_task_panel_never_shows_a_task_from_another_workspace(): void
    {
        $outsider = User::create(['name' => 'Other Owner', 'email' => 'owner@othercorp.test', 'password' => 'Secret123!']);
        $other = \App\Models\Workspace::create(['name' => 'OtherCorp', 'owner_id' => $outsider->id]);
        $other->users()->attach($outsider->id, ['role' => 'owner']);
        $project = Project::create(['workspace_id' => $other->id, 'name' => 'Secret Merger', 'owner_id' => $outsider->id, 'privacy' => 'workspace', 'color' => '#4573d2', 'status' => 'on_track']);
        $section = $project->sections()->create(['name' => 'Todo', 'position' => 1]);
        $secret = $project->tasks()->create(['title' => 'Confidential price', 'section_id' => $section->id, 'creator_id' => $outsider->id, 'position' => 1]);
        $secret->comments()->create(['user_id' => $outsider->id, 'body' => 'Board-only comment', 'is_internal' => true]);

        $this->actingAs(User::where('email', 'leo@taskflow.test')->first());

        // The browser can't point the panel at another task…
        try {
            Livewire::test(\App\Livewire\TaskPanel::class)->set('taskId', $secret->id);
            $this->fail('taskId should be locked');
        } catch (\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException) {
            $this->addToAssertionCount(1);
        }

        // …a crafted ?task= link opens nothing…
        Livewire::withQueryParams(['task' => $secret->id])->test(\App\Livewire\TaskPanel::class)
            ->assertSet('taskId', null)
            ->assertDontSee('Secret Merger')->assertDontSee('Board-only comment');

        // …and opening it directly is refused.
        Livewire::test(\App\Livewire\TaskPanel::class)->call('open', $secret->id)->assertForbidden();
    }

    public function test_only_admins_can_change_teams(): void
    {
        $leo = User::where('email', 'leo@taskflow.test')->first();
        $ws = $leo->currentWorkspace;
        $private = $ws->teams()->create(['name' => 'Leadership']);
        $project = Project::create(['workspace_id' => $ws->id, 'name' => 'Leadership Salaries', 'owner_id' => $ws->owner_id, 'team_id' => $private->id, 'privacy' => 'team', 'color' => '#e8384f', 'status' => 'on_track']);
        $anyTeam = $ws->teams()->whereHas('users', fn ($q) => $q->whereKey($leo->id))->first() ?? $private;

        $this->actingAs($leo);
        $teams = fn () => Livewire::test(\App\Livewire\Admin\Teams::class);
        $teams()->assertDontSee('New team')->assertDontSee('Add a member');

        // A member can't add themselves (or anyone) to a team, or change teams in any other way.
        $teams()->set('addUser.'.$private->id, (string) $leo->id)->call('addMember', $private->id)->assertForbidden();
        $teams()->call('removeMember', $anyTeam->id, $leo->id)->assertForbidden();
        $teams()->call('create')->assertForbidden();
        $teams()->call('edit', $private->id)->assertForbidden();
        $teams()->set('name', 'Renamed')->call('save')->assertForbidden();
        $this->assertFalse($private->users()->whereKey($leo->id)->exists());
        $this->assertFalse($project->isVisibleTo($leo->fresh()));
        $this->get(route('projects.show', $project))->assertForbidden();

        // Admins still can.
        $this->actingAs(User::where('email', 'admin@taskflow.test')->first());
        Livewire::test(\App\Livewire\Admin\Teams::class)
            ->set('addUser.'.$private->id, (string) $leo->id)->call('addMember', $private->id)->assertHasNoErrors();
        $this->assertTrue($private->users()->whereKey($leo->id)->exists());
    }

    public function test_only_admins_can_manage_clients_and_portal_invites(): void
    {
        $leo = User::where('email', 'leo@taskflow.test')->first();
        $client = $leo->currentWorkspace->clients()->first();
        $this->actingAs($leo);
        $page = fn () => Livewire::test(\App\Livewire\Admin\Clients::class);

        $page()->assertSee($client->name)->assertDontSee('New client')->assertDontSee('Invite to portal');
        $page()->call('create')->assertForbidden();
        $page()->call('edit', $client->id)->assertForbidden();
        $page()->set('form.name', 'Made by a member')->call('save')->assertForbidden();
        $page()->call('openInvite', $client->id)->assertForbidden();
        $page()->set('inviteFor', $client->id)->set('inviteEmail', 'outsider@evil.test')->call('sendInvite')->assertForbidden();
        $this->assertDatabaseMissing('clients', ['name' => 'Made by a member']);
        $this->assertDatabaseMissing('invitations', ['email' => 'outsider@evil.test']);

        $this->actingAs(User::where('email', 'admin@taskflow.test')->first());
        Livewire::test(\App\Livewire\Admin\Clients::class)->call('openInvite', $client->id)
            ->set('inviteEmail', 'new.contact@brightside.test')->call('sendInvite')->assertHasNoErrors();
        $this->assertDatabaseHas('invitations', ['email' => 'new.contact@brightside.test', 'role' => 'client']);
    }

    public function test_common_passwords_are_rejected(): void
    {
        foreach (['password', 'Password', '12345678', 'aaaaaaaa'] as $weak) {
            $this->post('/register', ['name' => 'Weak', 'email' => 'weak@example.com', 'workspace' => 'W', 'password' => $weak, 'password_confirmation' => $weak])
                ->assertSessionHasErrors('password');
        }
        $this->post('/register', ['name' => 'Strong', 'email' => 'strong@example.com', 'workspace' => 'S', 'password' => 'Cebu-Sunrise-2026', 'password_confirmation' => 'Cebu-Sunrise-2026'])
            ->assertSessionHasNoErrors();
    }

    public function test_invited_members_join_only_the_teams_an_admin_picks(): void
    {
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $ws = $admin->currentWorkspace;
        $teamProject = Project::where('workspace_id', $ws->id)->where('privacy', 'team')->whereNotNull('team_id')->firstOrFail();
        $this->actingAs($admin);

        // No team picked: the new member sees workspace-wide projects only.
        Livewire::test(\App\Livewire\Admin\Members::class)->call('openInvite')
            ->set('emails', 'plain@appyugo.test')->call('invite')->assertHasNoErrors();
        // Team picked: they join that team.
        Livewire::test(\App\Livewire\Admin\Members::class)->call('openInvite')
            ->set('emails', 'teamed@appyugo.test')->set('inviteTeams', [(string) $teamProject->team_id])->call('invite')->assertHasNoErrors();
        auth()->logout();

        foreach (['plain@appyugo.test' => false, 'teamed@appyugo.test' => true] as $email => $shouldSee) {
            $invitation = \App\Models\Invitation::where('email', $email)->firstOrFail();
            $this->post(route('invitations.accept', $invitation->token), ['name' => 'New Person', 'password' => 'Cebu-Sunrise-2026', 'password_confirmation' => 'Cebu-Sunrise-2026'])
                ->assertRedirect(route('dashboard'));
            $user = User::where('email', $email)->first();
            $this->assertSame('member', $user->role());
            $this->assertSame($shouldSee, $teamProject->isVisibleTo($user), $email);
            $this->assertSame($shouldSee ? 1 : 0, $user->teams()->count(), $email);
            $this->get(route('clients'))->assertForbidden();
            $this->get(route('home'))->assertDontSee(route('clients'));
            auth()->logout();
        }
    }

    /** A workspace-wide project Leo (member) and Jo can see, with time logged by two people. */
    protected function roleFixture(): array
    {
        $leo = User::where('email', 'leo@taskflow.test')->first();
        $jo = User::where('email', 'jo@taskflow.test')->first();
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $ws = $leo->currentWorkspace;
        $project = Project::create(['workspace_id' => $ws->id, 'name' => 'Roles Project', 'owner_id' => $admin->id, 'privacy' => 'workspace', 'color' => '#4573d2', 'status' => 'on_track']);
        $other = Project::create(['workspace_id' => $ws->id, 'name' => 'Not Jo\'s Project', 'owner_id' => $admin->id, 'privacy' => 'workspace', 'color' => '#4573d2', 'status' => 'on_track']);
        foreach ([$project, $other] as $p) {
            $section = $p->sections()->create(['name' => 'Todo', 'position' => 1]);
            $task = $p->tasks()->create(['title' => 'Shared task '.$p->id, 'section_id' => $section->id, 'creator_id' => $admin->id, 'position' => 1, 'assignee_id' => $admin->id]);
            $p->tasks()->create(['title' => 'Leo task '.$p->id, 'section_id' => $section->id, 'creator_id' => $admin->id, 'position' => 2, 'assignee_id' => $leo->id]);
            foreach ([$leo, $admin] as $u) {
                \App\Models\TimeEntry::create(['project_id' => $p->id, 'task_id' => $task->id, 'user_id' => $u->id,
                    'started_at' => now()->subHours(3), 'ended_at' => now()->subHours(2), 'duration_seconds' => 3600, 'note' => 'Time by '.$u->name]);
            }
        }
        $ws->users()->updateExistingPivot($jo->id, ['role' => 'manager']);
        $project->members()->attach($jo->id); // Jo is part of Roles Project only

        return [$leo, $jo->fresh(), $admin, $project, $other];
    }

    public function test_members_work_in_projects_but_cannot_manage_them_or_the_workspace(): void
    {
        [$leo, , , $project] = $this->roleFixture();
        $this->actingAs($leo);

        // Can do the work…
        Livewire::test(\App\Livewire\Projects\ListView::class, ['project' => $project])
            ->set('newTask.'.$project->sections()->first()->id, 'Member task')->call('addTask', $project->sections()->first()->id)->assertHasNoErrors();
        $this->assertDatabaseHas('tasks', ['title' => 'Member task', 'project_id' => $project->id]);
        Livewire::test(\App\Livewire\Projects\Docs::class, ['project' => $project])->call('create')->assertHasNoErrors();

        // …but not change the project.
        $show = fn () => Livewire::test(\App\Livewire\Projects\Show::class, ['project' => $project, 'view' => 'list']);
        $show()->assertDontSee('Edit project details')->assertDontSee('Custom fields');
        $show()->call('openEdit')->assertForbidden();
        $show()->call('setStatus', 'at_risk')->assertForbidden();
        $show()->call('openFields')->assertForbidden();
        Livewire::test(\App\Livewire\Projects\ListView::class, ['project' => $project])->set('newSection', 'Hack')->call('addSection')->assertForbidden();
        $ov = fn () => Livewire::test(\App\Livewire\Projects\Overview::class, ['project' => $project]);
        $ov()->call('saveDescription')->assertForbidden();
        $ov()->set('updateTitle', 'x')->call('postUpdate')->assertForbidden();
        $ov()->set('addMember', (string) $leo->id)->call('addProjectMember')->assertForbidden();
        $ov()->set('resourceUrl', 'example.com')->call('addLink')->assertForbidden();
        Livewire::test(\App\Livewire\Projects\Index::class)->assertDontSee('New project')->call('openCreate')->assertForbidden();

        // No workspace modules.
        foreach (['teams', 'members', 'clients', 'settings'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('home'))->assertDontSee(route('teams'))->assertDontSee(route('members'));

        // Reports and timesheets: only their own data.
        Livewire::test(\App\Livewire\Reports\Index::class)->set('report', 'incomplete')->set('person', '')->call('expandAll', true)
            ->assertSee('Leo task '.$project->id)->assertDontSee('Shared task '.$project->id)->assertDontSee('Projects by status');
        Livewire::test(\App\Livewire\Reports\Index::class)->set('report', 'timesheet')->set('range', 'last_7')->call('expandAll', true)
            ->assertSee('Time by '.$leo->name)->assertDontSee('Time by Alex Rivera');
        Livewire::test(\App\Livewire\Projects\Timesheet::class, ['project' => $project])->set('range', 'last_7')->set('person', '')
            ->assertSee('Time by '.$leo->name)->assertDontSee('Time by Alex Rivera')->assertSee('Your time only');
    }

    public function test_managers_manage_only_the_projects_they_belong_to(): void
    {
        [, $jo, , $project, $other] = $this->roleFixture();
        $this->actingAs($jo);

        // Their project: full control and everyone's data.
        Livewire::test(\App\Livewire\Projects\Show::class, ['project' => $project, 'view' => 'list'])->assertSee('Edit project details')
            ->call('setStatus', 'at_risk')->assertHasNoErrors();
        $this->assertSame('at_risk', $project->fresh()->status);
        Livewire::test(\App\Livewire\Projects\Overview::class, ['project' => $project])->set('addMember', (string) User::where('email', 'leo@taskflow.test')->value('id'))->call('addProjectMember')->assertHasNoErrors();
        Livewire::test(\App\Livewire\Projects\Timesheet::class, ['project' => $project])->set('range', 'last_7')
            ->assertSee('Time by Leo Tan')->assertSee('Time by Alex Rivera');

        // A workspace-wide project they're not part of: can see and work, not manage.
        Livewire::test(\App\Livewire\Projects\Show::class, ['project' => $other, 'view' => 'list'])->call('setStatus', 'at_risk')->assertForbidden();
        Livewire::test(\App\Livewire\Projects\Timesheet::class, ['project' => $other])->set('range', 'last_7')->assertDontSee('Time by Alex Rivera');

        // Reports cover their projects, everyone in them.
        Livewire::test(\App\Livewire\Reports\Index::class)->set('report', 'timesheet')->set('range', 'last_7')->call('expandAll', true)
            ->assertSee('Time by Alex Rivera')->assertSee('Roles Project')->assertDontSee('Not Jo');

        // Can create projects; no workspace modules.
        $this->assertTrue($jo->canCreateProjects());
        $this->get(route('members'))->assertForbidden();
    }

    public function test_changes_broadcast_one_live_update_per_project(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key']);
        \Illuminate\Support\Facades\Event::fake([\App\Events\ProjectChanged::class]);

        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $task = $web->tasks()->create(['title' => 'Live one', 'section_id' => $web->sections()->first()->id]);
        $task->comments()->create(['body' => 'hi', 'is_internal' => true]);
        \App\Support\Realtime::flush();

        \Illuminate\Support\Facades\Event::assertDispatchedTimes(\App\Events\ProjectChanged::class, 1);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\ProjectChanged::class, function ($e) use ($web, $task) {
            $channels = array_map(fn ($c) => $c->name, $e->broadcastOn());

            return $e->projectId === $web->id
                && in_array($task->id, $e->broadcastWith()['taskIds'], true)
                && $channels === ['private-project.'.$web->id, 'private-workspace.'.$web->workspace_id]
                && ! array_key_exists('title', $e->broadcastWith()); // no task content in the payload
        });
    }
}
