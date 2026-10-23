<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** The workspace itself changed (name, project list). Carries no content. */
class WorkspaceChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public int $workspaceId, public ?string $actor = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspace.'.$this->workspaceId)];
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    public function broadcastWith(): array
    {
        return ['projectId' => null, 'taskIds' => [], 'actor' => $this->actor];
    }
}
