<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MailStatus;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MailSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@taskflow.test')->first());
    }

    public function test_settings_shows_gmail_setup_and_sends_a_test_email(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.port' => 587]);
        Mail::fake();

        Livewire::test(\App\Livewire\Admin\Settings::class)
            ->assertSee('Google (Gmail SMTP)')
            ->call('sendTestEmail')
            ->assertSee('Sent to admin@taskflow.test');
        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.mail_test']);
    }

    public function test_test_email_explains_when_email_is_not_set_up(): void
    {
        config(['mail.default' => 'log']);
        Livewire::test(\App\Livewire\Admin\Settings::class)->assertSee('Not set up')
            ->call('sendTestEmail')->assertSee('Outgoing email isn’t set up yet');
    }

    public function test_mail_errors_are_explained_in_plain_language(): void
    {
        config(['mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.port' => 587]);
        $this->assertStringContainsString('app password', MailStatus::explain(new \RuntimeException('Expected response code "235" but got code "535", with message "535-5.7.8 Username and Password not accepted."')));
        $this->assertStringContainsString('Pro plan', MailStatus::explain(new \RuntimeException('Connection could not be established with host "ssl://smtp.gmail.com:587": Network is unreachable')));
    }

    public function test_a_failed_confirmation_email_never_locks_a_new_account_out(): void
    {
        auth()->logout();
        // A mail server that can't be reached, with confirmation switched on.
        config(['taskflow.verify_email' => true, 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);

        $this->post('/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'workspace' => 'Ana Co', 'password' => 'Cebu-Sunrise-2026', 'password_confirmation' => 'Cebu-Sunrise-2026'])
            ->assertRedirect(route('home'));
        $this->assertTrue(User::where('email', 'ana@example.com')->first()->hasVerifiedEmail());
        $this->get('/home')->assertOk();

        // Password reset with the server down still answers normally.
        auth()->logout();
        $this->post('/forgot-password', ['email' => 'ana@example.com'])->assertSessionHas('status');
    }
}
