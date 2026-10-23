<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TimerBar;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The running-timer bar at the top of the app. */
class TimerBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_stop_logs_the_time_and_names_the_task(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $me = User::factory()->create();
        $task = Task::factory()->create(['title' => 'Homepage hero']);
        $entry = TimeEntry::factory()->onTask($task)->by($me)->create(['started_at' => '2026-10-20 09:15:00', 'ended_at' => null, 'duration_seconds' => 0]);
        $this->actingAs($me);

        Livewire::test(TimerBar::class)
            ->call('stop')
            ->assertDispatched('timer-changed')
            ->assertDispatched('task-saved')
            ->assertDispatched('toast', message: 'Logged 45m on “Homepage hero”.', type: 'success', undo: null);

        $this->assertSame(2700, $entry->fresh()->duration_seconds);
    }

    public function test_stop_with_nothing_running_says_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TimerBar::class)->call('stop')->assertNotDispatched('toast');
    }

    public function test_shows_only_my_running_timer(): void
    {
        $me = User::factory()->create();
        TimeEntry::factory()->onTask(Task::factory()->create(['title' => 'Colleague work']))->running()->create();
        $this->actingAs($me);

        Livewire::test(TimerBar::class)->assertViewHas('entry', null);
    }
}
