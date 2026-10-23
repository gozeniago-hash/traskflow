<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

trait InteractsWithWorkspace
{
    protected function me(): User
    {
        return auth()->user();
    }

    protected function workspace(): Workspace
    {
        return $this->me()->currentWorkspace;
    }

    /** A toast message; pass an Undo token to offer an Undo button. */
    protected function toast(string $message, string $type = 'success', ?string $undo = null): void
    {
        $this->dispatch('toast', message: $message, type: $type, undo: $undo ? route('undo', $undo) : null);
    }

    /** After a checkbox click: completed tasks leave the list at once, with Undo on the toast. */
    protected function completedToast(Task $task): void
    {
        if ($task->isCompleted()) {
            $this->dispatch('toast', message: 'Marked '.$task->ticket().' complete.', type: 'success', undo: route('tasks.reopen', $task));
        }
    }

    protected function requireAdmin(): void
    {
        abort_unless($this->me()->isAdmin(), 403, 'Only workspace admins can do that.');
    }
}
