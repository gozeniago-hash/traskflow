<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Deletes a client. Its contacts lose portal access at once; its projects are kept. */
class DeleteClient
{
    public function handle(Client $client): void
    {
        DB::transaction(function () use ($client): void {
            $contactIds = $client->workspace->users()->wherePivot('client_id', $client->id)->wherePivot('role', 'client')->pluck('users.id');
            $client->workspace->users()->detach($contactIds);
            User::whereKey($contactIds)->where('current_workspace_id', $client->workspace_id)->update(['current_workspace_id' => null]);
            $client->delete();
        });

        Audit::log('client.deleted', 'Deleted client “'.$client->name.'”', $client);
    }
}
