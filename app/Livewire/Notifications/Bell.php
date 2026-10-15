<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Support\NotificationFeed;
use Livewire\Attributes\On;
use Livewire\Component;

/** The bell in the header: unread count, and the latest changes when opened. */
class Bell extends Component
{
    use InteractsWithWorkspace;

    public bool $open = false;

    /** What counted as "new" when the panel was opened, so highlights survive marking as read. */
    public ?string $newSince = null;

    public function toggle(): void
    {
        $this->open = ! $this->open;
        if ($this->open) {
            $feed = NotificationFeed::for($this->me());
            $this->newSince = $feed->readAt()->toIso8601String();
            $feed->markAllRead(); // opening the panel counts as seeing them
        }
    }

    #[On('task-saved')]
    public function refresh(): void
    {
        // re-render: the count is computed in render()
    }

    public function render()
    {
        $feed = NotificationFeed::for($this->me());

        return view('livewire.notifications.bell', [
            'feed' => $feed,
            'count' => $this->open ? 0 : $feed->unreadCount(),
            'items' => $this->open ? $feed->latest(12) : collect(),
            'since' => $this->newSince ? \Carbon\Carbon::parse($this->newSince) : null,
            'allUrl' => $this->me()->isClient() ? route('portal.notifications') : route('notifications'),
        ]);
    }
}
