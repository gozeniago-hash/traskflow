<?php

namespace App\Livewire\Concerns;

/**
 * Livewire listener names for live updates (see App\Support\Realtime).
 * Components return these from getListeners() so they re-render when data changes elsewhere.
 */
trait LiveUpdates
{
    protected function projectChannel(int $projectId): string
    {
        return 'echo-private:project.'.$projectId.',.changed';
    }

    protected function workspaceChannel(): string
    {
        return 'echo-private:workspace.'.(int) auth()->user()?->current_workspace_id.',.changed';
    }

    /** Default handler: nothing to do, the re-render picks up the changes. */
    public function onRemoteChange($event = null): void
    {
    }
}
