<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use App\Support\NotificationFeed;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Notifications')]
class Index extends Component
{
    use InteractsWithWorkspace;

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $kind = '';

    #[Url(except: false)]
    public bool $mine = false;

    public int $perPage = 40;

    public ?string $newSince = null;

    public function mount(): void
    {
        $feed = NotificationFeed::for($this->me());
        $this->newSince = $feed->readAt()->toIso8601String();
        $feed->markAllRead();
    }

    public function updated(): void
    {
        $this->perPage = 40;
    }

    public function more(): void
    {
        $this->perPage += 40;
    }

    public function render()
    {
        $me = $this->me();
        $feed = NotificationFeed::for($me);
        $items = $feed->latest($this->perPage + 1, $this->project !== '' ? (int) $this->project : null, $this->kind ?: null, $this->mine);
        $tz = config('taskflow.timezone');

        $kinds = NotificationFeed::KINDS;
        if ($me->isClient()) {
            $kinds = array_intersect_key($kinds, array_flip(['task', 'comment', 'file', 'doc', 'update', 'resource']));
        }

        return view('livewire.notifications.index', [
            'feed' => $feed,
            'groups' => $items->take($this->perPage)->groupBy(function ($a) use ($tz) {
                $d = $a->created_at->timezone($tz);

                return $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('l, F j'));
            }),
            'hasMore' => $items->count() > $this->perPage,
            'projects' => Project::query()->visibleTo($me)->orderBy('name')->get(['projects.id', 'name']),
            'kinds' => $kinds,
            'since' => Carbon::parse($this->newSince),
        ])->layout($me->isClient() ? 'layouts.portal' : 'layouts.app');
    }
}
