<?php

namespace App\Actions\Time;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Stops whatever timer the person has running and logs the time. */
class StopTimer
{
    /** Returns the entry that was stopped, or null when nothing was running. */
    public function handle(User $user): ?TimeEntry
    {
        return DB::transaction(function () use ($user): ?TimeEntry {
            User::whereKey($user->id)->lockForUpdate()->first();

            return $this->stopRunning($user);
        });
    }

    /** Stops every running entry of the person; the caller holds the lock on their user row. */
    public function stopRunning(User $user): ?TimeEntry
    {
        $entries = TimeEntry::query()->running()->where('user_id', $user->id)->get();

        foreach ($entries as $entry) {
            $entry->forceFill([
                'ended_at' => now(),
                'duration_seconds' => $entry->elapsedSeconds(),
            ])->save();
        }

        return $entries->last();
    }
}
