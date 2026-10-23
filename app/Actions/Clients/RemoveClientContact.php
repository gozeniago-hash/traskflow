<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\User;
use App\Support\Audit;

/** Takes away a client contact's portal access at once. */
class RemoveClientContact
{
    public function handle(Client $client, int $userId): void
    {
        $detached = $client->workspace->users()->wherePivot('client_id', $client->id)->wherePivot('role', 'client')->detach($userId);
        if (! $detached) {
            return;
        }

        // Signed in elsewhere, they would otherwise keep this workspace open.
        User::whereKey($userId)->where('current_workspace_id', $client->workspace_id)->update(['current_workspace_id' => null]);
        Audit::log('client.contact_removed', 'Removed portal access for '.User::find($userId)?->email, $client, ['user_id' => $userId]);
    }
}
