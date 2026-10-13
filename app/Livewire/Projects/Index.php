<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Projects')]
class Index extends Component
{
    use InteractsWithWorkspace;

    #[Url]
    public bool $new = false;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: 'all')]
    public string $scope = 'all'; // all | mine

    public array $form = [];

    /** Once someone types their own key we stop suggesting one from the name. */
    public bool $keyEdited = false;

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'key' => '',
            'description' => '',
            'color' => Project::COLORS[array_rand(Project::COLORS)],
            'team_id' => (string) ($this->me()->teams()->where('workspace_id', $this->workspace()->id)->value('teams.id') ?? ''),
            'client_id' => '',
            'privacy' => 'team',
            'layout' => 'board',
            'portal_enabled' => false,
            'due_date' => '',
        ];
    }

    public function updatedForm($value, $field): void
    {
        if ($field === 'name' && ! $this->keyEdited) {
            $this->form['key'] = trim((string) $value) === '' ? '' : Project::generateKey((string) $value, $this->workspace()->id);
        }
        if ($field === 'key') {
            $this->keyEdited = true;
            $this->form['key'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
        }
    }

    public function openCreate(): void
    {
        $this->keyEdited = false;
        $this->resetForm();
        $this->resetValidation();
        $this->new = true;
    }

    public function create()
    {
        $this->authorize('create', Project::class);
        $ws = $this->workspace();

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.key' => ['nullable', 'regex:/^[A-Z][A-Z0-9]{1,9}$/', Rule::unique('projects', 'key')->where('workspace_id', $ws->id)],
            'form.description' => ['nullable', 'string', 'max:5000'],
            'form.color' => ['required', Rule::in(Project::COLORS)],
            'form.team_id' => ['nullable', Rule::exists('teams', 'id')->where('workspace_id', $ws->id)],
            'form.client_id' => ['nullable', Rule::exists('clients', 'id')->where('workspace_id', $ws->id)],
            'form.privacy' => ['required', Rule::in(array_keys(Project::PRIVACY))],
            'form.layout' => ['required', Rule::in(['board', 'list', 'client'])],
            'form.portal_enabled' => ['boolean'],
            'form.due_date' => ['nullable', 'date'],
        ], [
            'form.key.regex' => 'Use 2–10 letters or numbers, starting with a letter (e.g. WEB, APP2).',
            'form.key.unique' => 'Another project in this workspace already uses that ID.',
        ])['form'];

        $project = DB::transaction(function () use ($data, $ws) {
            $project = $ws->projects()->create([
                'name' => $data['name'],
                'key' => $data['key'] ?: null,
                'description' => $data['description'] ?: null,
                'color' => $data['color'],
                'team_id' => $data['team_id'] ?: null,
                'client_id' => $data['client_id'] ?: null,
                'owner_id' => $this->me()->id,
                'privacy' => $data['privacy'],
                'default_view' => $data['layout'] === 'board' ? 'board' : 'list',
                'portal_enabled' => $data['client_id'] ? (bool) $data['portal_enabled'] : false,
                'due_date' => $data['due_date'] ?: null,
            ]);
            $project->members()->attach($this->me()->id);

            $sections = match ($data['layout']) {
                'board' => ['To do', 'In progress', 'In review', 'Done'],
                'client' => ['Discovery', 'Design', 'Build', 'Client review', 'Launch'],
                default => ['To do'],
            };
            foreach ($sections as $i => $name) {
                $project->sections()->create(['name' => $name, 'position' => $i + 1]);
            }

            return $project;
        });

        $this->new = false;
        session()->flash('status', 'Project created. Add your first tasks!');

        return $this->redirectRoute('projects.show', ['project' => $project->id], navigate: true);
    }

    public function render()
    {
        $me = $this->me();

        $projects = Project::query()->visibleTo($me)->active()
            ->with(['team', 'client', 'owner'])
            ->withCount([
                'topLevelTasks as tasks_total',
                'topLevelTasks as tasks_done' => fn ($q) => $q->whereNotNull('completed_at'),
            ])
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->team !== '', fn ($q) => $q->where('team_id', $this->team))
            ->when($this->scope === 'mine', fn ($q) => $q->where(fn ($w) => $w->where('owner_id', $me->id)
                ->orWhereHas('members', fn ($m) => $m->where('users.id', $me->id))))
            ->orderBy('name')
            ->get();

        return view('livewire.projects.index', [
            'projects' => $projects,
            'teams' => $this->workspace()->teams()->orderBy('name')->get(),
            'clients' => $this->workspace()->clients()->orderBy('name')->get(),
        ]);
    }
}
