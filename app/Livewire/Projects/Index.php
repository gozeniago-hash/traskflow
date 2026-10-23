<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use App\Models\ProjectField;
use App\Support\ProjectLog;
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
    use InteractsWithWorkspace, LiveUpdates;

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

    protected function getListeners(): array
    {
        return [$this->workspaceChannel() => 'onRemoteChange'];
    }

    public function mount(): void
    {
        $this->new = $this->new && $this->me()->canCreateProjects();
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
            'fields' => [], // [{name, type, options: "A, B, C"}]
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

    public function addFieldRow(?string $preset = null): void
    {
        $p = $preset ? (ProjectField::PRESETS[$preset] ?? null) : null;
        $this->form['fields'][] = [
            'name' => $p['name'] ?? '',
            'type' => $p['type'] ?? 'text',
            'options' => $p ? implode(', ', array_keys($p['options'])) : '',
            'preset' => $preset,
        ];
    }

    public function removeFieldRow(int $i): void
    {
        unset($this->form['fields'][$i]);
        $this->form['fields'] = array_values($this->form['fields']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Project::class);
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
            'form.fields' => ['array', 'max:20'],
            'form.fields.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'form.fields.*.type' => ['required', Rule::in(array_keys(ProjectField::TYPES))],
            'form.fields.*.options' => ['nullable', 'string', 'max:1000'],
        ], [
            'form.fields.*.name.required' => 'Give each custom field a name (or remove the row).',
            'form.fields.*.name.distinct' => 'Two custom fields have the same name.',
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
                ProjectLog::quietly(fn () => $project->sections()->create(['name' => $name, 'position' => $i + 1])); // starter sections aren't news
            }

            foreach (array_values($data['fields'] ?? []) as $i => $row) {
                $hasOptions = in_array($row['type'], ['select', 'multiselect'], true);
                $presetColors = ProjectField::PRESETS[$this->form['fields'][$i]['preset'] ?? '']['options'] ?? [];
                $labels = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) ($row['options'] ?? ''))))));

                // Keep preset colours where the label matches; other options get automatic colours.
                $options = [];
                foreach ($labels as $label) {
                    if (isset($presetColors[$label])) {
                        $options[$label] = $presetColors[$label];
                    } else {
                        $options[] = $label;
                    }
                }

                $project->fields()->create([
                    'name' => trim($row['name']),
                    'type' => $hasOptions && ! $labels ? 'text' : $row['type'],
                    'options' => $hasOptions && $labels ? ProjectField::makeOptions($options) : null,
                    'position' => $i + 1,
                ]);
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
