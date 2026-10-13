<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * "Something in this project changed — refresh." Deliberately carries no task content:
 * each listening page re-renders through its normal permission checks.
 */
class ProjectChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(
        public int $projectId,
        public int $workspaceId,
        public array $taskIds = [],
        public ?string $actor = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.'.$this->projectId),
            new PrivateChannel('workspace.'.$this->workspaceId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    public function broadcastWith(): array
    {
        return [
            'projectId' => $this->projectId,
            'taskIds' => array_values(array_slice($this->taskIds, 0, 50)),
            'actor' => $this->actor,
        ];
    }
}
