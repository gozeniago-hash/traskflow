<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\GithubRepository;
use App\Support\GitHub;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    use InteractsWithWorkspace;

    public string $name = '';

    public string $githubToken = '';
    public string $newRepo = '';
    public ?int $showWebhookFor = null;

    public function mount(): void
    {
        $this->requireAdmin();
        $this->name = $this->workspace()->name;
    }

    /** Outcome of the last test email, shown in the Email card. */
    public ?array $mailTest = null;

    public function sendTestEmail(): void
    {
        $this->requireAdmin();
        $me = $this->me();
        $key = 'mail-test:'.$me->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 3)) {
            $this->mailTest = ['ok' => false, 'message' => 'Wait a few minutes before sending another test email.'];

            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 600);

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->mailTest = ['ok' => false, 'message' => 'Outgoing email isn’t set up yet, so nothing was sent.'];

            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "This is a test from TaskFlow.\n\nIf you can read this, invitations, password resets and email confirmations will reach people too.",
                fn ($m) => $m->to($me->email)->subject('TaskFlow test email')
            );
            \App\Support\Audit::log('workspace.mail_test', 'Sent a test email to '.$me->email, $this->workspace());
            $this->mailTest = ['ok' => true, 'message' => 'Sent to '.$me->email.'. Check your inbox (and spam folder).'];
        } catch (\Throwable $e) {
            report($e);
            $this->mailTest = ['ok' => false, 'message' => \App\Support\MailStatus::explain($e)];
        }
    }

    public function setRequireTwoFactor(bool $on): void
    {
        $this->requireAdmin();
        if ($on && ! $this->me()->hasTwoFactor()) {
            $this->toast('Turn on two-step verification for your own account first (Profile), so you don’t lock yourself out.', 'error');

            return;
        }
        $this->workspace()->update(['require_two_factor' => $on]);
        \App\Support\Audit::log($on ? 'workspace.two_factor_required' : 'workspace.two_factor_optional',
            $on ? 'Required two-step verification for everyone on the team' : 'Made two-step verification optional', $this->workspace());
        $this->toast($on ? 'Two-step verification is now required. Teammates without it will be asked to set it up.' : 'Two-step verification is now optional.');
    }

    public function save(): void
    {
        $this->requireAdmin();
        $this->validate(['name' => ['required', 'string', 'max:120']]);
        $old = $this->workspace()->name;
        $this->workspace()->update(['name' => $this->name]);
        if ($old !== $this->name) {
            \App\Support\Audit::log('workspace.renamed', 'Renamed the workspace from “'.$old.'” to “'.$this->name.'”', $this->workspace());
        }
        $this->dispatch('workspace-updated');
        $this->toast('Workspace renamed.');
    }

    public function render()
    {
        $ws = $this->workspace();

        return view('livewire.admin.settings', [
            'stats' => [
                'Members' => $ws->members()->count(),
                'Client contacts' => $ws->clientUsers()->count(),
                'Teams' => $ws->teams()->count(),
                'Active projects' => $ws->projects()->active()->count(),
                'Archived projects' => $ws->projects()->whereNotNull('archived_at')->count(),
            ],
            'archived' => $ws->projects()->whereNotNull('archived_at')->orderBy('name')->get(),
            'storage' => ['used' => $ws->storageUsed(), 'quota' => $ws->storageQuota()],
            'mail' => \App\Support\MailStatus::summary(),
            'security' => [
                'required' => (bool) $ws->require_two_factor,
                'without' => $ws->members()->get()->reject->hasTwoFactor()->count(),
            ],
            'github' => [
                'connected' => filled($ws->github_token),
                'login' => $ws->github_login,
                'repos' => $ws->githubRepositories()->withCount('pullRequests')->get(),
            ],
        ]);
    }

    public function unarchive(int $projectId): void
    {
        $this->requireAdmin();
        $project = $this->workspace()->projects()->findOrFail($projectId);
        $project->update(['archived_at' => null]);
        \App\Support\Audit::log('project.restored', 'Restored archived project “'.$project->name.'”', $project);
        $this->dispatch('project-updated');
        $this->toast('Project restored.');
    }

    // ---- GitHub ----------------------------------------------------------

    public function connectGitHub(): void
    {
        $this->requireAdmin();
        $this->validate(['githubToken' => ['required', 'string', 'max:255']], [], ['githubToken' => 'token']);
        $ws = $this->workspace();
        try {
            $login = GitHub::for($ws)->verifyToken(trim($this->githubToken));
        } catch (\Throwable $e) {
            $this->addError('githubToken', $e instanceof \RuntimeException ? $e->getMessage() : "Couldn't reach GitHub. Try again in a moment.");

            return;
        }
        $ws->forceFill(['github_token' => trim($this->githubToken), 'github_login' => $login])->save();
        $this->githubToken = '';
        \App\Support\Audit::log('github.connected', 'Connected GitHub as @'.$login, $this->workspace());
        $this->toast('GitHub connected as @'.$login.'.');
    }

    public function disconnectGitHub(): void
    {
        $this->requireAdmin();
        $ws = $this->workspace();
        $ws->forceFill(['github_token' => null, 'github_login' => null])->save();
        \App\Support\Audit::log('github.disconnected', 'Disconnected GitHub', $this->workspace());
        $this->toast('GitHub disconnected. Linked pull requests stay on tasks until you remove the repositories.');
    }

    public function addRepository(): void
    {
        $this->requireAdmin();
        $ws = $this->workspace();
        // Accept "owner/repo" or a pasted github.com URL.
        $name = trim(preg_replace('#^(https?://)?(www\.)?github\.com/#i', '', trim($this->newRepo)), '/ ');
        $name = preg_replace('#\.git$#', '', $name);
        $this->newRepo = $name;
        $this->validate(['newRepo' => ['required', 'regex:#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#']], ['newRepo.regex' => 'Use the owner/repository form, e.g. appyugo/taskflow.'], ['newRepo' => 'repository']);

        $gh = GitHub::for($ws);
        if (! $gh->connected()) {
            $this->addError('newRepo', 'Connect GitHub first.');

            return;
        }
        try {
            $name = $gh->verifyRepository($name);
        } catch (\Throwable $e) {
            $this->addError('newRepo', $e instanceof \RuntimeException ? $e->getMessage() : "Couldn't reach GitHub. Try again in a moment.");

            return;
        }
        if ($ws->githubRepositories()->where('full_name', $name)->exists()) {
            $this->addError('newRepo', 'That repository is already added.');

            return;
        }

        $repo = $ws->githubRepositories()->create(['full_name' => $name, 'webhook_secret' => Str::random(40)]);
        \App\Support\Audit::log('github.repository_added', 'Added GitHub repository '.$name, $repo);
        $hooked = $gh->installWebhook($repo);
        $this->newRepo = '';
        try {
            $count = $gh->sync($repo);
            $this->toast("Added {$name} and linked its pull requests ({$count} checked).");
        } catch (\Throwable $e) {
            $this->toast("Added {$name}, but the first sync failed: ".$e->getMessage(), 'error');
        }
        if (! $hooked) {
            $this->showWebhookFor = $repo->id;
        }
    }

    public function syncRepository(int $id): void
    {
        $this->requireAdmin();
        $repo = $this->repo($id);
        try {
            $count = GitHub::for($this->workspace())->sync($repo);
            $this->toast("Synced {$repo->full_name} ({$count} pull requests checked).");
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function removeRepository(int $id): void
    {
        $this->requireAdmin();
        $repo = $this->repo($id);
        GitHub::for($this->workspace())->removeWebhook($repo);
        $repo->delete();
        \App\Support\Audit::log('github.repository_removed', 'Removed GitHub repository '.$repo->full_name, $repo);
        $this->toast('Repository removed.');
    }

    public function toggleWebhookHelp(int $id): void
    {
        $this->showWebhookFor = $this->showWebhookFor === $id ? null : $id;
    }

    protected function repo(int $id): GithubRepository
    {
        return $this->workspace()->githubRepositories()->findOrFail($id);
    }
}
