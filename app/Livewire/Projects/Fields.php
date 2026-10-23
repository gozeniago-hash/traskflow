<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use App\Models\ProjectField;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Project settings → Custom fields. */
class Fields extends Component
{
    use InteractsWithWorkspace;

    public Project $project;

    #[Locked] // set only by server actions that check access
    public ?int $editingId = null;

    public bool $showForm = false;

    public array $form = [];

    public string $newOption = '';

    public function mount(Project $project): void
    {
        $this->authorize('update', $project);
        $this->project = $project;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '', 'type' => 'select', 'description' => '',
            'required' => false, 'show_on_card' => true, 'client_visible' => false,
            'options' => [],
        ];
        $this->newOption = '';
        $this->resetValidation();
    }

    protected function field(int $id): ProjectField
    {
        return $this->project->fields()->findOrFail($id);
    }

    public function create(): void
    {
        $this->authorize('update', $this->project);
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function addPreset(string $key): void
    {
        $this->authorize('update', $this->project);
        $preset = ProjectField::PRESETS[$key] ?? null;
        if (! $preset) {
            return;
        }
        $this->editingId = null;
        $this->resetForm();
        $this->form['name'] = $preset['name'];
        $this->form['type'] = $preset['type'];
        $this->form['options'] = ProjectField::makeOptions($preset['options']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $f = $this->field($id);
        $this->editingId = $f->id;
        $this->form = [
            'name' => $f->name, 'type' => $f->type, 'description' => (string) $f->description,
            'required' => $f->required, 'show_on_card' => $f->show_on_card, 'client_visible' => $f->client_visible,
            'options' => $f->optionList(),
        ];
        $this->newOption = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function addOption(): void
    {
        $label = trim($this->newOption);
        if ($label === '') {
            return;
        }
        $colors = array_keys(ProjectField::COLORS);
        $color = $colors[(count($this->form['options']) + 1) % count($colors)];
        $this->form['options'][] = ProjectField::makeOptions([$label => $color])[0];
        $this->newOption = '';
    }

    public function removeOption(int $index): void
    {
        unset($this->form['options'][$index]);
        $this->form['options'] = array_values($this->form['options']);
    }

    public function moveOption(int $index, int $dir): void
    {
        $j = $index + $dir;
        if (! isset($this->form['options'][$index], $this->form['options'][$j])) {
            return;
        }
        [$this->form['options'][$index], $this->form['options'][$j]] = [$this->form['options'][$j], $this->form['options'][$index]];
    }

    public function setOptionColor(int $index, string $color): void
    {
        if (isset($this->form['options'][$index]) && array_key_exists($color, ProjectField::COLORS)) {
            $this->form['options'][$index]['color'] = $color;
        }
    }

    public function save(): void
    {
        $this->authorize('update', $this->project);
        if (trim($this->newOption) !== '') {
            $this->addOption();
        }

        $editing = $this->editingId ? $this->field($this->editingId) : null;
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:80',
                Rule::unique('project_fields', 'name')->where('project_id', $this->project->id)->ignore($editing?->id)],
            'form.type' => ['required', Rule::in(array_keys(ProjectField::TYPES))],
            'form.description' => ['nullable', 'string', 'max:200'],
            'form.required' => ['boolean'],
            'form.show_on_card' => ['boolean'],
            'form.client_visible' => ['boolean'],
            'form.options' => ['array', 'max:50'],
            'form.options.*.label' => ['required', 'string', 'max:60'],
        ], [
            'form.name.unique' => 'This project already has a field with that name.',
            'form.options.*.label.required' => 'Options need a label.',
        ])['form'];

        $type = $editing ? $editing->type : $data['type']; // type is fixed once created
        if (in_array($type, ['select', 'multiselect'], true) && ! $this->cleanOptions($this->form['options'] ?? [])) {
            $this->addError('newOption', 'Add at least one option.');

            return;
        }

        $attributes = [
            'name' => trim($data['name']),
            'type' => $type,
            'description' => $data['description'] ?: null,
            'required' => (bool) $data['required'],
            'show_on_card' => (bool) $data['show_on_card'],
            'client_visible' => (bool) $data['client_visible'] && $this->project->portal_enabled,
            // Use the full option rows from the form: validated data only keeps the validated keys (label).
            'options' => in_array($type, ['select', 'multiselect'], true) ? $this->cleanOptions($this->form['options'] ?? []) : null,
        ];

        if ($editing) {
            $editing->update($attributes);
            $this->toast('Field updated.');
        } else {
            $this->project->fields()->create($attributes + ['position' => (int) $this->project->fields()->max('position') + 1]);
            $this->toast('Field added. It now appears on every task in this project.');
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->dispatch('fields-updated');
    }

    /** Keep only well-formed options; give any option without an id/colour a fresh one. */
    protected function cleanOptions(array $options): array
    {
        $out = [];
        $seen = [];
        foreach (array_values($options) as $o) {
            $label = trim((string) ($o['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $id = (string) ($o['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                $id = ProjectField::makeOptions([$label])[0]['id'];
            }
            $seen[$id] = true;
            $color = (string) ($o['color'] ?? 'gray');
            $out[] = ['id' => $id, 'label' => mb_substr($label, 0, 60), 'color' => array_key_exists($color, ProjectField::COLORS) ? $color : 'gray'];
        }

        return $out;
    }

    public function delete(int $id): void
    {
        $this->authorize('update', $this->project);
        $f = $this->field($id);
        $f->delete();
        $this->showForm = false;
        $this->editingId = null;
        $this->toast('“'.$f->name.'” removed from all tasks.');
        $this->dispatch('fields-updated');
    }

    public function move(int $id, int $dir): void
    {
        $this->authorize('update', $this->project);
        $fields = $this->project->fields()->get()->values();
        $i = $fields->search(fn ($f) => $f->id === $id);
        $j = $i + ($dir < 0 ? -1 : 1);
        if ($i === false || ! isset($fields[$j])) {
            return;
        }
        $ordered = $fields->all();
        [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];
        foreach ($ordered as $pos => $f) {
            $f->update(['position' => $pos + 1]);
        }
        $this->dispatch('fields-updated');
    }

    public function render()
    {
        return view('livewire.projects.fields', [
            'fields' => $this->project->fields()->withCount('values')->get(),
            'editing' => $this->editingId ? $this->project->fields()->find($this->editingId) : null,
        ]);
    }
}
