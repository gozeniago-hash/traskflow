<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->role() !== null && ! $user->isClient();
    }

    public function update(User $user, Project $project): bool
    {
        return ! $user->isClient() && $project->isVisibleTo($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->workspace_id === $user->current_workspace_id
            && ($user->isAdmin() || $project->owner_id === $user->id);
    }
}
