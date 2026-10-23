<?php

namespace App\Models;

use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends BaseModel
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected $fillable = ['project_id', 'task_id', 'user_id', 'started_at', 'ended_at', 'duration_seconds', 'note', 'is_manual'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'is_manual' => 'boolean',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** Seconds so far, including the live portion of a running timer. */
    public function elapsedSeconds(): int
    {
        return $this->isRunning()
            ? max(0, now()->getTimestamp() - $this->started_at->getTimestamp())
            : $this->duration_seconds;
    }

    /** "2h 05m", "45m", "0m" */
    public static function formatShort(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h ? sprintf('%dh %02dm', $h, $m) : $m.'m';
    }

    /** "1:02:09" */
    public static function formatClock(int $seconds): string
    {
        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Parse "1h 30m", "1.5h", "45m", "1:30" or a bare number of minutes into seconds.
     */
    public static function parseDuration(string $input): ?int
    {
        $input = strtolower(trim($input));
        if ($input === '') {
            return null;
        }
        if (preg_match('/^(\d+):(\d{1,2})$/', $input, $m)) {
            return ((int) $m[1] * 60 + (int) $m[2]) * 60;
        }
        if (preg_match('/^\d+$/', $input)) {
            return (int) $input * 60;
        }

        $seconds = 0;
        $matched = false;
        if (preg_match('/(\d+(?:\.\d+)?)\s*h/', $input, $m)) {
            $seconds += (int) round((float) $m[1] * 3600);
            $matched = true;
        }
        if (preg_match('/(\d+)\s*m/', $input, $m)) {
            $seconds += (int) $m[1] * 60;
            $matched = true;
        }

        return $matched ? $seconds : null;
    }
}
