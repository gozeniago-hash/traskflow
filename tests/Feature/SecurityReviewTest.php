<?php

namespace Tests\Feature;

use App\Http\Middleware\UseNearestForwardedIp;
use App\Livewire\CreateTask;
use App\Livewire\Projects\Docs;
use App\Livewire\Reports\Index;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Export\Csv;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** Regression tests for the SAST / secure-code review findings. */
class SecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
    }

    /** Another company's workspace with one project, doc and doc revision. */
    protected function otherCompany(): array
    {
        $owner = User::create(['name' => 'Other Owner', 'email' => 'owner@othercorp.test', 'password' => 'Secret123!']);
        $ws = Workspace::create(['name' => 'OtherCorp', 'owner_id' => $owner->id]);
        $ws->users()->attach($owner->id, ['role' => 'owner']);
        $project = Project::create(['workspace_id' => $ws->id, 'name' => 'Secret Merger', 'owner_id' => $owner->id, 'privacy' => 'workspace', 'color' => '#4e6a9e', 'status' => 'on_track']);
        $project->sections()->create(['name' => 'Board-only section', 'position' => 1]);
        $doc = $project->docs()->create(['title' => 'Merger terms', 'content' => '<p>Confidential price: 9M</p>']);
        $revision = $doc->revisions()->create(['title' => 'Merger terms', 'content' => '<p>Confidential price: 9M</p>', 'user_id' => $owner->id]);

        return [$owner, $project, $revision];
    }

    public function test_doc_history_cannot_preview_another_companys_revision(): void
    {
        [, , $foreign] = $this->otherCompany();
        $this->actingAs(User::where('email', 'leo@taskflow.test')->first());
        $web = Project::where('name', 'Brightside Website Redesign')->first();

        $docs = Livewire::test(Docs::class, ['project' => $web]);

        // The id can't be planted from the browser…
        try {
            $docs->set('previewRevisionId', $foreign->id);
            $this->fail('previewRevisionId should be locked');
        } catch (CannotUpdateLockedPropertyException) {
        }
        // …and asking for it through the action is refused.
        Livewire::test(Docs::class, ['project' => $web])
            ->call('previewRevision', $foreign->id)->assertNotFound();
    }

    public function test_new_task_form_never_shows_another_companys_project(): void
    {
        [, $foreign] = $this->otherCompany();
        $this->actingAs(User::where('email', 'leo@taskflow.test')->first());

        Livewire::test(CreateTask::class)->call('open', $foreign->id)->assertForbidden();

        $form = Livewire::test(CreateTask::class);
        try {
            $form->set('projectId', $foreign->id);
            $this->fail('projectId should be locked');
        } catch (CannotUpdateLockedPropertyException) {
        }
        $form->set('show', true)->assertDontSee('Board-only section')->assertDontSee('Secret Merger');
    }

    public function test_csv_exports_cannot_carry_spreadsheet_formulas(): void
    {
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $this->actingAs($admin);
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $task = $web->tasks()->whereNull('parent_id')->whereNull('completed_at')->first();
        $task->update(['title' => '=HYPERLINK("http://evil.example","Click")', 'assignee_id' => $admin->id]);

        $response = Livewire::test(Index::class)->call('export');
        $csv = base64_decode($response->effects['download']['content']);
        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK(', $csv);

        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame('-42', Csv::cell('-42')); // real numbers stay numbers
    }

    public function test_visitors_cannot_fake_their_address_to_dodge_rate_limits(): void
    {
        // The nearest public hop wins; spoofed values (left) and proxy hops (private ranges) are ignored.
        $this->assertSame('9.9.9.9', UseNearestForwardedIp::nearestPublic(['1.1.1.1', ' 9.9.9.9', '100.64.3.4', '10.0.0.7']));
        $this->assertNull(UseNearestForwardedIp::nearestPublic(['not-an-ip', '192.168.1.1']));

        // Five sign-ups a minute per address, however the left part of the header is varied.
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->withHeaders(['X-Forwarded-For' => "203.0.{$i}.{$i}, 8.8.4.4"])
                ->post('/register', ['name' => 'N', 'email' => "n{$i}@example.com", 'workspace' => 'W', 'password' => 'x', 'password_confirmation' => 'x']);
        }
        $response->assertSessionHasErrors(['email' => 'Too many sign-ups from your network. Wait a minute and try again.']);
    }

    public function test_person_fields_only_accept_people_on_the_team(): void
    {
        [$outsider] = $this->otherCompany();
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $field = $web->fields()->create(['name' => 'Reviewer', 'type' => 'person', 'position' => 9]);
        $client = User::where('email', 'client@brightside.test')->first();
        $maya = User::where('email', 'maya@taskflow.test')->first();

        $check = fn ($id) => Validator::make(['v' => $id], ['v' => $field->rules()])->passes();
        $this->assertTrue($check($maya->id));
        $this->assertFalse($check($outsider->id), 'someone from another company');
        $this->assertFalse($check($client->id), 'a client contact');
    }

    /** What the DAST scan confirmed, pinned so a config change can't quietly undo it. */
    public function test_cookies_and_headers_match_the_dast_baseline(): void
    {
        $response = $this->get('/login')->assertOk();

        $session = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === config('session.cookie'));
        $this->assertNotNull($session);
        $this->assertTrue($session->isHttpOnly(), 'session cookie must be HttpOnly');
        $this->assertSame('lax', strtolower((string) $session->getSameSite()));

        foreach (['/login', '/this-page-does-not-exist'] as $path) {
            $r = $this->get($path);
            $r->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $csp = (string) $r->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("default-src 'self'", $csp, $path);
            $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp, $path);
            $this->assertStringNotContainsString("'unsafe-inline'", explode(';', explode('script-src', $csp)[1])[0], 'no inline scripts');
        }

        // Guests sent to sign in get a bare redirect, nothing from the page they asked for.
        $this->get('/projects')->assertRedirect('/login');
        $this->assertStringNotContainsString('Brightside', $this->get('/projects')->getContent());
    }

    public function test_audit_log_rows_only_accept_known_columns(): void
    {
        $row = new AuditLog(['action' => 'x.y', 'description' => 'd', 'id' => 999, 'created_at' => '2000-01-01']);
        $this->assertSame('x.y', $row->action);
        $this->assertNull($row->id, 'id must not be mass-assignable');
        $this->assertNull($row->created_at, 'timestamps must not be mass-assignable');
    }
}
