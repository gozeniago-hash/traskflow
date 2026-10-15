<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Support\NotificationFeed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bell in the header. Opening and closing happen in the browser (instantly, animated);
 * the server only loads the list — ahead of time when the pointer reaches the bell.
 */
class Bell extends Component
{
    use InteractsWithWorkspace;

    /** The list has been loaded at least once (afterwards it refreshes in place). */
    public bool $loaded = false;

    /** What counted as "new" when the panel was last opened, so highlights survive marking as read. */
    public ?string $newSince = null;

    /** Load the list without marking anything read (pointer is heading for the bell). */
    public function prefetch(): void
    {
        if (! $this->loaded) {
            $this->newSince = NotificationFeed::for($this->me())->readAt()->toIso8601String();
            $this->loaded = true;
        }
    }

    /** The panel was opened: load (if needed) and count everything as seen. */
    public function opened(): void
    {
        $feed = NotificationFeed::for($this->me());
        $this->newSince = $feed->readAt()->toIso8601String();
        $this->loaded = true;
        $feed->markAllRead();
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
            'count' => $feed->unreadCount(),
            'items' => $this->loaded ? $feed->latest(12) : collect(),
            'since' => $this->newSince ? \Carbon\Carbon::parse($this->newSince) : null,
            'allUrl' => $this->me()->isClient() ? route('portal.notifications') : route('notifications'),
        ]);
    }
}
