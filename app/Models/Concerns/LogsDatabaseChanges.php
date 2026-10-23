<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records every create, update and delete in the `activity_log` table as a debugging trail.
 * Secrets are excluded globally in config/activitylog.php and per model through $hidden.
 */
trait LogsDatabaseChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept($this->getHidden())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
