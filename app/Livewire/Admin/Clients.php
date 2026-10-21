<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Client;
use App\Support\Invites;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Clients')]
class Clients extends Component
{
    use InteractsWithWorkspace;

    public bool $showForm = false;
    #[\Livewire\Attributes\Locked] // set only by server actions that check access
    public ?int $editingId = null;
    public array $form = ['name' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'website' => '', 'notes' => ''];

    #[\Livewire\Attributes\Locked] // set only by server actions that check access
    public ?int $inviteFor = null;
    public string $inviteEmail = '';
    public ?string $lastInviteUrl = null;

    /*
     * Clients and their portal invitations are admin-only: an invitation gives an
     * outside person access to that client's shared projects.
     */

    public function create(): void
    {
        $this->requireAdmin();
        $this->reset(['editingId', 'form']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $client = $this->client($id);
        $this->editingId = $client->id;
        $this->form = $client->only(array_keys($this->form));
        $this->form = array_map(fn ($v) => (string) $v, $this->form);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->requireAdmin();
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.contact_name' => ['nullable', 'string', 'max:150'],
            'form.email' => ['nullable', 'email', 'max:190'],
            'form.phone' => ['nullable', 'string', 'max:50'],
            'form.website' => ['nullable', 'string', 'max:190'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ])['form'];

        if ($this->editingId) {
            $client = $this->client($this->editingId);
            $client->update($data);
            \App\Support\Audit::log('client.updated', 'Updated client “'.$client->name.'”', $client);
            $this->toast('Client updated.');
        } else {
            $client = $this->workspace()->clients()->create($data);
            \App\Support\Audit::log('client.created', 'Added client “'.$client->name.'”', $client);
            $this->toast('Client added.');
        }
        $this->showForm = false;
    }

    public function openInvite(int $clientId): void
    {
        $this->requireAdmin();
        $client = $this->client($clientId);
        $this->inviteFor = $client->id;
        $this->inviteEmail = (string) $client->email;
        $this->lastInviteUrl = null;
        $this->resetValidation();
    }

    public function sendInvite(): void
    {
        $this->requireAdmin();
        $this->validate(['inviteEmail' => ['required', 'email', 'max:190']]);
        $client = $this->client($this->inviteFor);

        $existing = $this->workspace()->users()->where('email', strtolower($this->inviteEmail))->first();
        if ($existing) {
            $this->addError('inviteEmail', 'That person is already in this workspace.');

            return;
        }

        if (Invites::remaining($this->workspace()) < 1) {
            $this->addError('inviteEmail', 'This workspace can send '.Invites::PER_HOUR.' invitations an hour. Try again later.');

            return;
        }
        $invitation = Invites::send($this->workspace(), $this->me(), $this->inviteEmail, 'client', $client->id);
        $this->lastInviteUrl = $invitation->url();
        \App\Support\Audit::log('invitation.sent', 'Invited '.$invitation->email.' to the '.$client->name.' client portal', $invitation, ['role' => 'client']);
        $this->toast('Invitation sent to '.$invitation->email.'.');
    }

    public function revokeInvite(int $invitationId): void
    {
        $this->requireAdmin();
        $invitation = $this->workspace()->invitations()->whereKey($invitationId)->whereNull('accepted_at')->first();
        if ($invitation) {
            $invitation->delete();
            \App\Support\Audit::log('invitation.revoked', 'Revoked the portal invitation for '.$invitation->email, $invitation);
        }
        $this->toast('Invitation revoked.');
    }

    public function removeContact(int $clientId, int $userId): void
    {
        $this->requireAdmin();
        $this->client($clientId);
        $this->workspace()->users()->wherePivot('client_id', $clientId)->wherePivot('role', 'client')->detach($userId);
        \App\Support\Audit::log('client.contact_removed', 'Removed portal access for '.\App\Models\User::find($userId)?->email, $this->client($clientId), ['user_id' => $userId]);
        $this->toast('Portal access removed.');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $client = $this->client($id);
        $this->workspace()->users()->wherePivot('client_id', $client->id)->wherePivot('role', 'client')->detach();
        $client->delete();
        \App\Support\Audit::log('client.deleted', 'Deleted client “'.$client->name.'”', $client);
        $this->toast('Client deleted. Their projects were kept.');
    }

    public function closeInvite(): void
    {
        $this->inviteFor = null;
    }

    protected function client(?int $id): Client
    {
        return $this->workspace()->clients()->findOrFail($id);
    }

    public function render()
    {
        $clients = $this->workspace()->clients()
            ->withCount('projects')
            ->with([
                'invitations' => fn ($q) => $q->pending(),
                'contacts' => fn ($q) => $q->where('workspace_user.workspace_id', $this->workspace()->id),
                'projects' => fn ($q) => $q->active()->orderBy('name'),
            ])
            ->orderBy('name')->get();

        return view('livewire.admin.clients', ['clients' => $clients, 'isAdmin' => $this->me()->isAdmin()]);
    }
}
