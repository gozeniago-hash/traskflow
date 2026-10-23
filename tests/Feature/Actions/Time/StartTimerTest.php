<?php

namespace Tests\Feature\Actions\Time;

use App\Actions\Time\StartTimer;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Starting a timer: one running timer per person, whatever they click. */
class StartTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_on_another_task_stops_the_running_timer_and_logs_its_time(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $user = User::factory()->create();
        $first = Task::factory()->create();
        $second = Task::factory()->create();
        $running = app(StartTimer::class)->handle($first, $user);
        $this->travelTo('2026-10-20 09:25:00');

        app(StartTimer::class)->handle($second, $user);

        $this->assertSame(1500, $running->fresh()->duration_seconds);
        $this->assertSame([$second->id], TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->pluck('task_id')->all());
    }

    public function test_a_second_click_on_start_keeps_the_timer_already_counting(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $first = app(StartTimer::class)->handle($task, $user);

        $second = app(StartTimer::class)->handle($task, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TimeEntry::count());
    }

    public function test_two_timers_left_running_from_before_are_both_stopped_when_one_starts(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        TimeEntry::factory()->onTask($task)->by($user)->running()->create();
        TimeEntry::factory()->onTask(Task::factory()->create())->by($user)->running()->create();

        $started = app(StartTimer::class)->handle($task, $user);

        $this->assertSame([$started->id], TimeEntry::whereNull('ended_at')->pluck('id')->all());
    }

    public function test_starting_leaves_other_peoples_timers_alone(): void
    {
        $user = User::factory()->create();
        $colleagues = TimeEntry::factory()->running()->create();

        app(StartTimer::class)->handle(Task::factory()->create(), $user);

        $this->assertNull($colleagues->fresh()->ended_at);
    }
}
