<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Support\Audit;
use App\Support\Invites;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Members')]
class Members extends Component
{
    use InteractsWithWorkspace;

    public bool $showInvite = false;

    public string $emails = '';

    public string $role = 'member';

    public array $inviteTeams = [];

    public array $sentLinks = [];

    public function openInvite(): void
    {
        $this->requireAdmin();
        $this->reset(['emails', 'role', 'sentLinks', 'inviteTeams']);
        $this->resetValidation();
        $this->showInvite = true;
    }

    public function invite(): void
    {
        $this->requireAdmin();
        $this->validate([
            'emails' => ['required', 'string'],
            'role' => ['required', 'in:admin,manager,member'],
            'inviteTeams' => ['array'],
            'inviteTeams.*' => [Rule::exists('teams', 'id')->where('workspace_id', $this->workspace()->id)],
        ]);

        $list = collect(preg_split('/[\s,;]+/', $this->emails))->filter()->map(fn ($e) => strtolower(trim($e)))->unique();
        $invalid = $list->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($invalid->isNotEmpty()) {
            $this->addError('emails', 'Not a valid email: '.$invalid->implode(', '));

            return;
        }

        $existing = $this->workspace()->users()->whereIn('email', $list)->pluck('email');
        if ($list->diff($existing)->count() > Invites::remaining($this->workspace())) {
            $this->addError('emails', 'This workspace can send '.Invites::PER_HOUR.' invitations an hour. Try again later, or send fewer at once.');

            return;
        }
        $this->sentLinks = [];
        foreach ($list->diff($existing) as $email) {
            $invitation = Invites::send($this->workspace(), $this->me(), $email, $this->role, null, $this->inviteTeams);
            $this->sentLinks[$email] = $invitation->url();
            Audit::log('invitation.sent', 'Invited '.$email.' as '.$this->role, $invitation, ['role' => $this->role, 'teams' => $this->inviteTeams]);
        }

        $this->emails = '';
        $this->toast(count($this->sentLinks).' invitation(s) sent.'.($existing->isNotEmpty() ? ' Skipped existing members.' : ''));
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->requireAdmin();
        abort_unless(in_array($role, ['admin', 'manager', 'member'], true), 422);

        $target = $this->workspace()->members()->findOrFail($userId);
        if ($target->pivot->role === 'owner') {
            $this->toast('The owner role cannot be changed.', 'error');

            return;
        }
        $this->workspace()->users()->updateExistingPivot($userId, ['role' => $role]);
        Audit::log('member.role_changed', 'Changed '.$target->name.'’s role from '.$target->pivot->role.' to '.$role, $target, ['from' => $target->pivot->role, 'to' => $role]);
        $this->toast($target->name.' is now '.($role === 'admin' ? 'an admin' : 'a '.$role).'.');
    }

    public function remove(int $userId): void
    {
        $this->requireAdmin();
        $target = $this->workspace()->members()->findOrFail($userId);
        if ($target->pivot->role === 'owner' || $target->id === $this->me()->id) {
            $this->toast('You cannot remove the owner or yourself.', 'error');

            return;
        }

        $workspace = $this->workspace();
        $workspace->users()->detach($userId);
        foreach ($workspace->teams as $team) {
            $team->users()->detach($userId);
        }
        $target->projects()->detach($workspace->projects()->pluck('id'));
        if ($target->current_workspace_id === $workspace->id) {
            $target->forceFill(['current_workspace_id' => null])->save();
        }
        Audit::log('member.removed', 'Removed '.$target->name.' ('.$target->email.') from the workspace', $target);
        $this->toast($target->name.' was removed from the workspace.');
    }

    public function revoke(int $invitationId): void
    {
        $this->requireAdmin();
        $invitation = $this->workspace()->invitations()->whereKey($invitationId)->whereNull('accepted_at')->first();
        if ($invitation) {
            $invitation->delete();
            Audit::log('invitation.revoked', 'Revoked the invitation for '.$invitation->email, $invitation);
        }
        $this->toast('Invitation revoked.');
    }

    public function render()
    {
        $workspace = $this->workspace();

        return view('livewire.admin.members', [
            'members' => $workspace->members()->withCount(['assignedTasks as open_tasks_count' => fn ($q) => $q->whereNull('completed_at')->whereIn('project_id', $workspace->projects()->select('id'))])
                ->orderByRaw("case workspace_user.role when 'owner' then 0 when 'admin' then 1 else 2 end")->orderBy('name')->get(),
            'pending' => $workspace->invitations()->pending()->where('role', '!=', 'client')->latest()->get(),
            'isAdmin' => $this->me()->isAdmin(),
            'allTeams' => $workspace->teams()->withCount('projects')->orderBy('name')->get(),
        ]);
    }
}
