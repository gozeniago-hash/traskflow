<?php

namespace Tests\Feature\Actions\Time;

use App\Actions\Time\StopTimer;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Stopping the running timer. */
class StopTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_the_time_since_the_timer_started(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $user = User::factory()->create();
        $entry = TimeEntry::factory()->by($user)->create(['started_at' => '2026-10-20 08:15:00', 'ended_at' => null, 'duration_seconds' => 0]);

        $stopped = app(StopTimer::class)->handle($user);

        $this->assertSame($entry->id, $stopped->id);
        $this->assertSame(2700, $entry->fresh()->duration_seconds);
        $this->assertSame('2026-10-20 09:00:00', $entry->fresh()->ended_at->toDateTimeString());
    }

    public function test_a_second_click_on_stop_changes_nothing(): void
    {
        $user = User::factory()->create();
        TimeEntry::factory()->by($user)->running()->create();
        app(StopTimer::class)->handle($user);

        $second = app(StopTimer::class)->handle($user);

        $this->assertNull($second);
        $this->assertSame(1, TimeEntry::count());
    }
}
