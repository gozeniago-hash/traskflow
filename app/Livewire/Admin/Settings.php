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

    public function save(): void
    {
        $this->requireAdmin();
        $this->validate(['name' => ['required', 'string', 'max:120']]);
        $this->workspace()->update(['name' => $this->name]);
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
        $this->workspace()->projects()->findOrFail($projectId)->update(['archived_at' => null]);
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
        $this->toast('GitHub connected as @'.$login.'.');
    }

    public function disconnectGitHub(): void
    {
        $this->requireAdmin();
        $ws = $this->workspace();
        $ws->forceFill(['github_token' => null, 'github_login' => null])->save();
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
