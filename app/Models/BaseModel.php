<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ClearsBuilderCache;
use App\Models\Concerns\LogsDatabaseChanges;
use Illuminate\Database\Eloquent\Model;

/** Every app model extends this; User uses the same traits directly. */
abstract class BaseModel extends Model
{
    use ClearsBuilderCache;
    use LogsDatabaseChanges;
}
