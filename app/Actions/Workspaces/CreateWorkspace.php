<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a workspace owned by the person, with a "General" team they are in, and opens it.
 * Their user row is locked while it checks the name, so a double click creates one workspace.
 */
class CreateWorkspace
{
    /**
     * @throws ValidationException when they already own a workspace with that name
     */
    public function handle(User $owner, string $name): Workspace
    {
        $name = trim($name);

        return DB::transaction(function () use ($owner, $name): Workspace {
            User::whereKey($owner->id)->lockForUpdate()->first();
            if (Workspace::where('owner_id', $owner->id)->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['name' => 'You already have a workspace called “'.$name.'”.']);
            }

            $workspace = Workspace::create(['name' => $name, 'owner_id' => $owner->id]);
            $workspace->users()->attach($owner->id, ['role' => 'owner']);
            $workspace->teams()->create(['name' => 'General'])->users()->attach($owner->id);
            $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
            $owner->forgetMembership();

            return $workspace;
        });
    }
}
