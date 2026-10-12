<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/** Adds a few logged hours to the demo workspace so the time tracker has something to show. */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('taskflow.seed_demo_data') || TimeEntry::query()->exists()) {
            return;
        }

        $samples = [
            ['Homepage wireframes', 'maya@taskflow.test', [3.5, 2.0, 1.25]],
            ['Visual design: homepage and pricing', 'maya@taskflow.test', [2.5, 1.75]],
            ['Audit current site analytics', 'leo@taskflow.test', [1.5]],
            ['Offline mode sync', 'leo@taskflow.test', [4.0, 3.0]],
            ['Campaign brief and budget', 'jo@taskflow.test', [2.0]],
        ];

        foreach ($samples as [$title, $email, $hours]) {
            $task = Task::where('title', $title)->first();
            $user = User::where('email', $email)->first();
            if (! $task || ! $user) {
                continue;
            }
            foreach ($hours as $i => $h) {
                $start = now()->subDays(count($hours) - $i + 1)->setTime(9 + $i, 0);
                TimeEntry::create([
                    'project_id' => $task->project_id,
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'started_at' => $start,
                    'ended_at' => $start->copy()->addSeconds((int) ($h * 3600)),
                    'duration_seconds' => (int) ($h * 3600),
                ]);
            }
        }
    }

    public function down(): void
    {
    }
};
