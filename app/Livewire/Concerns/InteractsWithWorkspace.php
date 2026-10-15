<?php

namespace App\Livewire\Concerns;

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

    protected function requireAdmin(): void
    {
        abort_unless($this->me()->isAdmin(), 403, 'Only workspace admins can do that.');
    }
}
