<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\TimeEntry;
use App\Support\TimeTracker;
use Livewire\Attributes\On;
use Livewire\Component;

/** The running-timer pill in the top bar. */
class TimerBar extends Component
{
    use InteractsWithWorkspace;

    #[On('timer-changed')]
    public function refreshTimer(): void {}

    public function stop(): void
    {
        $entry = TimeTracker::stop($this->me());
        $this->dispatch('timer-changed');
        $this->dispatch('task-saved');
        if ($entry) {
            $this->toast('Logged '.TimeEntry::formatShort($entry->duration_seconds).' on “'.$entry->task?->title.'”.');
        }
    }

    public function render()
    {
        return view('livewire.timer-bar', ['entry' => TimeTracker::running($this->me())]);
    }
}
