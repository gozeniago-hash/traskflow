<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Support\Facades\RateLimiter;

class Invites
{
    /** Invitation emails one workspace may send per hour. */
    public const PER_HOUR = 50;

    /** How many more invitations this workspace may send this hour. */
    public static function remaining(Workspace $workspace): int
    {
        return RateLimiter::remaining('invites:'.$workspace->id, self::PER_HOUR);
    }
}
