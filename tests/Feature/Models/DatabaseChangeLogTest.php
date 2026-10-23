<?php

namespace Tests\Feature\Models;

use App\Builders\BaseBuilder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity as DebugLog;
use Tests\TestCase;

/** BaseModel's debug trail (spatie/laravel-activitylog) and its Builder cache clearing. */
class DatabaseChangeLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_task_logs_its_old_and_new_values(): void
    {
        $task = Task::factory()->create(['title' => 'Draft homepage']);

        $task->update(['title' => 'Final homepage']);

        $log = DebugLog::forSubject($task)->forEvent('updated')->sole();
        $this->assertSame('Final homepage', $log->attribute_changes['attributes']['title']);
        $this->assertSame('Draft homepage', $log->attribute_changes['old']['title']);
    }

    public function test_secrets_never_reach_the_log(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'password' => 'a-new-long-password',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'name' => 'Renamed',
        ])->save();

        $logged = DebugLog::forSubject($user)->get()->toJson();
        $this->assertStringContainsString('Renamed', $logged);
        $this->assertStringNotContainsString('password', $logged);
        $this->assertStringNotContainsString('two_factor_secret', $logged);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $logged);
    }

    public function test_saving_without_changes_writes_no_log(): void
    {
        $task = Task::factory()->create();

        $task->update(['title' => $task->title]);

        $this->assertSame(0, DebugLog::forSubject($task)->forEvent('updated')->count());
    }

    public function test_cache_is_cleared_even_for_models_with_their_own_booted_method(): void
    {
        CountingBuilder::$cleared = 0;
        $task = TaskWithCountingBuilder::find(Task::factory()->create()->id);

        $task->update(['title' => 'Changed']);
        $task->delete();

        $this->assertSame(2, CountingBuilder::$cleared);
    }
}

/** @extends BaseBuilder<TaskWithCountingBuilder> */
class CountingBuilder extends BaseBuilder
{
    public static int $cleared = 0;

    public function clearCache(): static
    {
        static::$cleared++;

        return $this;
    }
}

/** Task (which defines its own booted()) with a Builder that counts cache clears. */
class TaskWithCountingBuilder extends Task
{
    protected $table = 'tasks';

    public function newEloquentBuilder($query): CountingBuilder
    {
        return new CountingBuilder($query);
    }
}
