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

    /**
     * Listeners for a project page: changes by other people (WebSocket) plus
     * project-updated, which this browser fires when a header or tab edits the
     * project itself, so sibling components on the same page refresh too.
     */
    protected function projectListeners(int $projectId): array
    {
        return [
            $this->projectChannel($projectId) => 'onRemoteChange',
            'project-updated' => 'onProjectUpdated',
        ];
    }

    public function onProjectUpdated(): void {}

    /** Default handler: nothing to do, the re-render picks up the changes. */
    public function onRemoteChange($event = null): void {}
}
