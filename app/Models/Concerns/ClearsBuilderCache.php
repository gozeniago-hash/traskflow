<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Builders\BaseBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Flushes the model's Builder cache tags after every create, update and delete.
 * A trait boot method, so it runs even when the model defines its own booted().
 */
trait ClearsBuilderCache
{
    protected static function bootClearsBuilderCache(): void
    {
        $clearCache = static function (Model $model): void {
            $builder = $model->newQuery();
            if ($builder instanceof BaseBuilder) {
                $builder->clearCache();
            }
        };

        static::created($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }
}
