<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Internal team members of a workspace (not client contacts).
Broadcast::channel('workspace.{workspaceId}', function (User $user, int $workspaceId) {
    return $user->workspaces()->whereKey($workspaceId)->wherePivot('role', '!=', 'client')->exists();
});

// Anyone who can see the project — including client contacts on portal-enabled projects.
Broadcast::channel('project.{projectId}', function (User $user, int $projectId) {
    $project = Project::find($projectId);

    return $project
        && (int) $project->workspace_id === (int) $user->current_workspace_id
        && $project->isVisibleTo($user);
});
