<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Teams')]
class Teams extends Component
{
    use InteractsWithWorkspace;

    public bool $showForm = false;

    #[Locked] // set only by server actions that check access
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public array $addUser = [];

    /*
     * Team membership decides who can see team projects, so every change to teams
     * is admin-only. Members can view teams but not create, edit or join them.
     */

    public function create(): void
    {
        $this->requireAdmin();
        $this->reset(['editingId', 'name', 'description']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $team = $this->team($id);
        $this->editingId = $team->id;
        $this->name = $team->name;
        $this->description = (string) $team->description;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->requireAdmin();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->editingId) {
            $team = $this->team($this->editingId);
            $team->update($data);
            Audit::log('team.updated', 'Updated team “'.$team->name.'”', $team);
            $this->toast('Team updated.');
        } else {
            $team = $this->workspace()->teams()->create($data);
            $team->users()->attach($this->me()->id);
            Audit::log('team.created', 'Created team “'.$team->name.'”', $team);
            $this->toast('Team created.');
        }

        $this->showForm = false;
    }

    public function addMember(int $teamId): void
    {
        $this->requireAdmin();
        $userId = (int) ($this->addUser[$teamId] ?? 0);
        if (! $userId || ! $this->workspace()->members()->whereKey($userId)->exists()) {
            return;
        }
        $team = $this->team($teamId);
        $team->users()->syncWithoutDetaching([$userId]);
        Audit::log('team.member_added', 'Added '.User::find($userId)?->name.' to team “'.$team->name.'”', $team, ['user_id' => $userId]);
        $this->addUser[$teamId] = '';
    }

    public function removeMember(int $teamId, int $userId): void
    {
        $this->requireAdmin();
        $team = $this->team($teamId);
        $team->users()->detach($userId);
        Audit::log('team.member_removed', 'Removed '.User::find($userId)?->name.' from team “'.$team->name.'”', $team, ['user_id' => $userId]);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $team = $this->team($id);
        $team->delete();
        Audit::log('team.deleted', 'Deleted team “'.$team->name.'”', $team);
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
