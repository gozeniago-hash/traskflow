<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Team;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Teams')]
class Teams extends Component
{
    use InteractsWithWorkspace;

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public array $addUser = [];

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $team = $this->team($id);
        $this->editingId = $team->id;
        $this->name = $team->name;
        $this->description = (string) $team->description;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->editingId) {
            $this->team($this->editingId)->update($data);
            $this->toast('Team updated.');
        } else {
            $team = $this->workspace()->teams()->create($data);
            $team->users()->attach($this->me()->id);
            $this->toast('Team created.');
        }

        $this->showForm = false;
    }

    public function addMember(int $teamId): void
    {
        $userId = (int) ($this->addUser[$teamId] ?? 0);
        if (! $userId || ! $this->workspace()->members()->whereKey($userId)->exists()) {
            return;
        }
        $this->team($teamId)->users()->syncWithoutDetaching([$userId]);
        $this->addUser[$teamId] = '';
    }

    public function removeMember(int $teamId, int $userId): void
    {
        $this->team($teamId)->users()->detach($userId);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->team($id)->delete();
        $this->toast('Team deleted. Its projects were kept.');
    }

    protected function team(int $id): Team
    {
        return $this->workspace()->teams()->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.admin.teams', [
            'teams' => $this->workspace()->teams()->with(['users' => fn ($q) => $q->orderBy('name')])->withCount('projects')->orderBy('name')->get(),
            'members' => $this->workspace()->members()->orderBy('name')->get(),
            'isAdmin' => $this->me()->isAdmin(),
        ]);
    }
}
