<?php

namespace Tests\Feature;

use App\Livewire\Admin\Members;
use App\Livewire\Admin\Settings;
use App\Livewire\Profile;
use App\Livewire\Projects\Files;
use App\Livewire\Projects\ListView;
use App\Livewire\TaskPanel;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Totp;
use App\Support\Undo;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** Fix-list items 9–16 from the QA report: limits, uploads, undo, 2FA, email verification, audit log. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@taskflow.test')->first();
    }

    protected function web(): Project
    {
        return Project::where('name', 'Brightside Website Redesign')->first();
    }

    // ------------------------------------------------------------------ #9 limits

    public function test_sign_ups_are_rate_limited_per_address(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/register', ['name' => 'N', 'email' => "n{$i}@example.com", 'workspace' => 'W', 'password' => 'x', 'password_confirmation' => 'x'])
                ->assertSessionHasErrors('password'); // weak password, but the request counted
        }
        $this->post('/register', ['name' => 'N', 'email' => 'n6@example.com', 'workspace' => 'W', 'password' => 'Cebu-Sunrise-2026', 'password_confirmation' => 'Cebu-Sunrise-2026'])
            ->assertSessionHasErrors(['email' => 'Too many sign-ups from your network. Wait a minute and try again.']);
        $this->assertDatabaseMissing('users', ['email' => 'n6@example.com']);
    }

    public function test_password_reset_emails_are_rate_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'admin@taskflow.test'])->assertSessionHasNoErrors();
        }
        $this->post('/forgot-password', ['email' => 'admin@taskflow.test'])->assertSessionHasErrors('email');
    }

    public function test_every_page_has_a_per_person_request_limit(): void
    {
        config(['taskflow.requests_per_minute' => 3]);
        $this->actingAs($this->admin());
        for ($i = 0; $i < 3; $i++) {
            $this->get('/home')->assertOk();
        }
        $this->get('/home')->assertStatus(429)->assertSee('Too many requests');
    }

    public function test_workspace_storage_quota_blocks_uploads_when_full(): void
    {
        Storage::fake('local');
        config(['taskflow.workspace_storage_mb' => 1]);
        $web = $this->web();
        $this->actingAs($this->admin());

        Livewire::test(Files::class, ['project' => $web])
            ->set('uploads', [UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf')])
            ->assertHasNoErrors();

        Attachment::create(['project_id' => $web->id, 'user_id' => $this->admin()->id, 'disk' => 'local', 'path' => 'x/big.bin', 'original_name' => 'big.zip', 'mime_type' => 'application/zip', 'size' => 2 * 1024 * 1024]);

        Livewire::test(Files::class, ['project' => $web])
            ->set('uploads', [UploadedFile::fake()->create('more.pdf', 10, 'application/pdf')])
            ->assertHasErrors('uploads.0');
        $this->assertDatabaseMissing('attachments', ['original_name' => 'more.pdf']);
    }

    // --------------------------------------------------------------- #14 uploads

    public function test_only_safe_file_types_can_be_uploaded(): void
    {
        Storage::fake('local');
        $web = $this->web();
        $this->actingAs($this->admin());

        foreach (['setup.exe', 'run.sh', 'page.html', 'shell.php', 'noextension'] as $name) {
            Livewire::test(Files::class, ['project' => $web])
                ->set('uploads', [UploadedFile::fake()->create($name, 5)])
                ->assertHasErrors('uploads.0');
            $this->assertDatabaseMissing('attachments', ['original_name' => $name]);
        }

        // A script renamed to .pdf is caught by its content.
        $disguised = UploadedFile::fake()->createWithContent('invoice.pdf', "<?php echo 'hi';");
        Livewire::test(Files::class, ['project' => $web])
            ->set('uploads', [$disguised])->assertHasErrors('uploads.0');

        Livewire::test(Files::class, ['project' => $web])
            ->set('uploads', [UploadedFile::fake()->create('Scope v2.docx', 20)])
            ->assertHasNoErrors();
        $this->assertDatabaseHas('attachments', ['original_name' => 'Scope v2.docx']);
    }

    // ------------------------------------------------------------------ #13 undo

    public function test_deleting_a_task_can_be_undone_with_everything_attached(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $web = $this->web();
        $this->actingAs($admin);

        $task = $web->tasks()->whereNull('parent_id')->whereHas('subtasks')->first();
        $sub = $task->subtasks()->first();
        $task->comments()->create(['user_id' => $admin->id, 'body' => 'Keep me']);
        TimeEntry::create(['project_id' => $web->id, 'task_id' => $sub->id, 'user_id' => $admin->id, 'started_at' => now()->subHour(), 'ended_at' => now(), 'duration_seconds' => 3600, 'is_manual' => true]);
        $file = Attachment::storeUpload(UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf'), $web, $admin, $task);

        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id)->call('deleteTask');
        $undoUrl = collect($panel->effects['dispatches'] ?? [])->firstWhere('name', 'toast')['params']['undo'] ?? null;
        $this->assertNotNull($undoUrl, 'the toast offers Undo');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $sub->id]);
        Storage::disk('local')->assertExists($file->path); // kept during the undo window

        // Only the person who deleted can undo.
        $this->actingAs(User::where('email', 'leo@taskflow.test')->first())->postJson($undoUrl)->assertStatus(410);

        $this->actingAs($admin)->postJson($undoUrl)->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'number' => $task->number]);
        $this->assertDatabaseHas('tasks', ['id' => $sub->id, 'parent_id' => $task->id]);
        $this->assertDatabaseHas('comments', ['task_id' => $task->id, 'body' => 'Keep me']);
        $this->assertDatabaseHas('time_entries', ['task_id' => $sub->id, 'duration_seconds' => 3600]);
        $this->assertDatabaseHas('attachments', ['id' => $file->id]);

        // A second undo of the same delete does nothing.
        $this->postJson($undoUrl)->assertStatus(410);
    }

    public function test_deleted_files_are_purged_once_the_undo_window_passes(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $web = $this->web();
        $this->actingAs($admin);
        $file = Attachment::storeUpload(UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'), $web, $admin);

        Livewire::test(Files::class, ['project' => $web])->call('delete', $file->id);
        Storage::disk('local')->assertExists($file->path);

        $this->travel(Undo::MINUTES + 1)->minutes();
        Undo::purgeExpired();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertSame(0, DB::table('undo_snapshots')->count());
    }

    public function test_deleting_a_section_can_be_undone(): void
    {
        $this->actingAs($this->admin());
        $web = $this->web();
        $section = $web->sections()->whereHas('tasks')->orderByDesc('position')->first();
        $taskIds = $section->tasks()->pluck('id')->all();

        $list = Livewire::test(ListView::class, ['project' => $web])->call('deleteSection', $section->id);
        $undoUrl = collect($list->effects['dispatches'] ?? [])->firstWhere('name', 'toast')['params']['undo'];
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);

        $this->postJson($undoUrl)->assertOk();
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'name' => $section->name]);
        $this->assertEqualsCanonicalizing($taskIds, Task::where('section_id', $section->id)->pluck('id')->all());
    }

    // ------------------------------------------------------------------- #15 2FA

    protected function enableTwoFactor(User $user): string
    {
        $this->actingAs($user);
        $profile = Livewire::test(Profile::class)->set('twoFactorPassword', 'password')->call('startTwoFactor')->assertHasNoErrors();
        $secret = session('two_factor.setup');
        $profile->set('setupCode', '000000')->call('confirmTwoFactor')->assertHasErrors('setupCode');
        $profile->set('setupCode', Totp::code($secret, Totp::currentStep()))->call('confirmTwoFactor')->assertHasNoErrors();
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->assertCount(10, $profile->get('shownRecoveryCodes'));
        auth()->logout();

        return $secret;
    }

    public function test_two_step_verification_is_asked_for_after_the_password(): void
    {
        $user = User::where('email', 'leo@taskflow.test')->first();
        $secret = $this->enableTwoFactor($user);
        $this->flushSession();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get('/home')->assertRedirect('/login');

        $this->post('/two-factor', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        // The code used to turn it on can't be replayed; the next one works.
        $this->post('/two-factor', ['code' => Totp::code($secret, Totp::currentStep() + 1)])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.two_factor_failed']);
    }

    public function test_recovery_codes_work_once(): void
    {
        $user = User::where('email', 'leo@taskflow.test')->first();
        $this->enableTwoFactor($user);
        $code = $user->fresh()->two_factor_recovery_codes[0];

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor', ['code' => $code])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_workspace_can_require_two_step_verification(): void
    {
        $admin = $this->admin();
        $member = User::where('email', 'leo@taskflow.test')->first();

        // An admin without it can't switch the requirement on (they'd lock themselves out).
        $this->actingAs($admin);
        Livewire::test(Settings::class)->call('setRequireTwoFactor', true);
        $this->assertFalse($admin->currentWorkspace->fresh()->require_two_factor);

        $this->enableTwoFactor($admin);
        $this->actingAs($admin);
        Livewire::test(Settings::class)->call('setRequireTwoFactor', true);
        $this->assertTrue($admin->currentWorkspace->fresh()->require_two_factor);

        $this->actingAs($member)->get('/home')->assertRedirect(route('profile'));
        $this->get('/profile')->assertOk()->assertSee('Your workspace requires this');
        // Clients aren't affected.
        $this->actingAs(User::where('email', 'client@brightside.test')->first())->get('/portal')->assertOk();
    }

    // ------------------------------------------------------- #15 email verification

    public function test_new_sign_ups_confirm_their_email_when_email_is_set_up(): void
    {
        config(['taskflow.verify_email' => true]);
        Notification::fake();

        $this->post('/register', ['name' => 'Vera', 'email' => 'vera@example.com', 'workspace' => 'V Co', 'password' => 'Cebu-Sunrise-2026', 'password_confirmation' => 'Cebu-Sunrise-2026'])
            ->assertRedirect(route('verification.notice'));
        $vera = User::where('email', 'vera@example.com')->first();
        Notification::assertSentTo($vera, VerifyEmail::class);

        $this->get('/home')->assertRedirect(route('verification.notice'));

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $vera->id, 'hash' => sha1($vera->email)]);
        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertTrue($vera->fresh()->hasVerifiedEmail());
        $this->get('/home')->assertOk();
    }

    public function test_email_verification_stays_off_without_a_mailer(): void
    {
        config(['taskflow.verify_email' => false]);
        $this->post('/register', ['name' => 'Nia', 'email' => 'nia@example.com', 'workspace' => 'N Co', 'password' => 'Cebu-Sunrise-2026', 'password_confirmation' => 'Cebu-Sunrise-2026'])
            ->assertRedirect(route('home'));
    }

    // ------------------------------------------------------------ #15 audit log

    public function test_admin_actions_are_recorded_in_the_audit_log(): void
    {
        $admin = $this->admin();
        $jo = User::where('email', 'jo@taskflow.test')->first();
        $this->actingAs($admin);

        Livewire::test(Members::class)->call('changeRole', $jo->id, 'manager');
        $entry = AuditLog::where('action', 'member.role_changed')->latest('id')->first();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertStringContainsString($jo->name, $entry->description);

        Livewire::test(\App\Livewire\Admin\AuditLog::class)->assertSee('member.role_changed')
            ->set('group', 'team')->assertDontSee('member.role_changed')
            ->call('export')->assertFileDownloaded();

        // Members can't read it.
        $this->actingAs(User::where('email', 'leo@taskflow.test')->first())->get('/audit-log')->assertForbidden();
    }
}
