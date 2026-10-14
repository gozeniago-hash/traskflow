<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    public Project $project;
    public string $view = 'list';

    public bool $showEdit = false;
    public bool $showFields = false;
    public array $form = [];

    protected function getListeners(): array
    {
        return $this->projectListeners($this->project->id);
    }

    public function mount(Project $project, ?string $view = null): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->view = array_key_exists((string) $view, Project::VIEWS) ? $view : ($project->default_view ?: 'list');
    }

    public function openFields(): void
    {
        $this->authorize('update', $this->project);
        $this->showEdit = false;
        $this->showFields = true;
    }

    public function setStatus(string $status): void
    {
        $this->authorize('update', $this->project);
        abort_unless(array_key_exists($status, Project::STATUSES), 422);
        $this->project->update(['status' => $status]);
        $this->dispatch('project-updated');
        $this->toast('Status set to '.Project::STATUSES[$status].'. Post an update on the Overview tab to explain why.');
    }

    public function openEdit(): void
    {
        $this->authorize('update', $this->project);
        $p = $this->project;
        $this->form = [
            'name' => $p->name,
            'key' => (string) $p->key,
            'description' => (string) $p->description,
            'color' => $p->color,
            'team_id' => (string) $p->team_id,
            'client_id' => (string) $p->client_id,
            'owner_id' => (string) $p->owner_id,
            'privacy' => $p->privacy,
            'default_view' => $p->default_view,
            'portal_enabled' => $p->portal_enabled,
            'start_date' => $p->start_date?->toDateString() ?? '',
            'due_date' => $p->due_date?->toDateString() ?? '',
        ];
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        $this->authorize('update', $this->project);
        $ws = $this->workspace();
        $this->form['key'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($this->form['key'] ?? '')));

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.key' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,9}$/', Rule::unique('projects', 'key')->where('workspace_id', $ws->id)->ignore($this->project->id)],
            'form.description' => ['nullable', 'string', 'max:5000'],
            'form.color' => ['required', Rule::in(Project::COLORS)],
            'form.team_id' => ['nullable', Rule::exists('teams', 'id')->where('workspace_id', $ws->id)],
            'form.client_id' => ['nullable', Rule::exists('clients', 'id')->where('workspace_id', $ws->id)],
            'form.owner_id' => ['nullable', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $ws->id)->whereNot('role', 'client')],
            'form.privacy' => ['required', Rule::in(array_keys(Project::PRIVACY))],
            'form.default_view' => ['required', Rule::in(array_keys(Project::VIEWS))],
            'form.portal_enabled' => ['boolean'],
            'form.start_date' => ['nullable', 'date'],
            'form.due_date' => ['nullable', 'date', 'after_or_equal:form.start_date'],
        ], [
            'form.key.regex' => 'Use 2–10 letters or numbers, starting with a letter (e.g. WEB, APP2).',
            'form.key.unique' => 'Another project in this workspace already uses that ID.',
        ])['form'];

        foreach (['team_id', 'client_id', 'owner_id', 'start_date', 'due_date'] as $key) {
            $data[$key] = $data[$key] ?: null;
        }
        if (! $data['client_id']) {
            $data['portal_enabled'] = false;
        }

        $this->project->update($data);
        $this->showEdit = false;
        $this->dispatch('project-updated');
        $this->toast('Project updated.');
    }

    public function archive(): void
    {
        $this->authorize('delete', $this->project);
        $this->project->update(['archived_at' => $this->project->archived_at ? null : now()]);
        $this->dispatch('project-updated');
        $this->toast($this->project->archived_at ? 'Project archived.' : 'Project restored.');
    }

    public function deleteProject()
    {
        $this->authorize('delete', $this->project);
        foreach ($this->project->attachments as $attachment) {
            $attachment->delete(); // removes the stored file too
        }
        $this->project->delete();
        session()->flash('status', 'Project deleted.');

        return $this->redirectRoute('projects.index', navigate: true);
    }

    public function render()
    {
        $ws = $this->workspace();

        return view('livewire.projects.show', [
            'members' => $this->project->members()->orderBy('name')->get(),
            'teams' => $ws->teams()->orderBy('name')->get(),
            'clients' => $ws->clients()->orderBy('name')->get(),
            'people' => $ws->members()->orderBy('name')->get(),
            'canDelete' => $this->me()->can('delete', $this->project),
            'canManage' => $this->me()->can('update', $this->project),
        ])->title($this->project->name);
    }
}
