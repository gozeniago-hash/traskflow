<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        if (! $task->project->isVisibleTo($user)) {
            return false;
        }

        return $user->isClient() ? $task->client_visible : true;
    }

    public function update(User $user, Task $task): bool
    {
        return ! $user->isClient() && $task->project->isVisibleTo($user);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function approve(User $user, Task $task): bool
    {
        return $user->isClient() && $this->view($user, $task) && $task->approval_status === 'pending';
    }
}
