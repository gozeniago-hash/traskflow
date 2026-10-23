<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Roles inside a workspace:
 * - admin / owner: everything
 * - manager: everything inside the projects they belong to (owner, project member or team)
 * - member: works inside shared projects (tasks, comments, files, docs, time) but can't
 *   change project details or settings
 * - client: portal only
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->isVisibleTo($user);
    }

    /** Do work inside the project: tasks, comments, files, docs, time. */
    public function contribute(User $user, Project $project): bool
    {
        return ! $user->isClient() && $project->isVisibleTo($user);
    }

    /** Change the project itself: details, status, sections, fields, members, sharing, updates. */
    public function update(User $user, Project $project): bool
    {
        return ! $user->isClient() && $project->isManagedBy($user);
    }

    /** Add, rename, reorder and delete sections: whoever manages the project, and its owner. */
    public function manageSections(User $user, Project $project): bool
    {
        return $this->manageContent($user, $project);
    }

    /** Delete anyone's tasks, files and docs: whoever manages the project, and its owner. Others delete only their own. */
    public function manageContent(User $user, Project $project): bool
    {
        return $this->update($user, $project)
            || (! $user->isClient() && $project->owner_id === $user->id && $project->isVisibleTo($user));
    }

    /** Choose what the client sees in the portal: tasks, files, docs and key resources. */
    public function shareWithClient(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /** See everyone's time and workload in this project (members see only their own). */
    public function viewTeamData(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->canCreateProjects();
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->workspace_id === $user->current_workspace_id
            && ($user->isAdmin() || ($user->isManager() && $project->owner_id === $user->id));
    }
}
