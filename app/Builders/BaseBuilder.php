<?php

declare(strict_types=1);

namespace App\Builders;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @template TModelClass of Model
 *
 * @extends Builder<TModelClass>
 */
abstract class BaseBuilder extends Builder
{
    /** @var array<int, string> Override in child builders to declare which cache tags this model belongs to */
    protected array $defaultCacheTags = [];

    // TTLs you can override per builder
    protected int $cacheTtl = 3600;

    protected int $cacheTtlShort = 1800;

    // -------------------------------------------------------------------------
    // Cached execution — available on every builder automatically
    // -------------------------------------------------------------------------

    /**
     * @param array<int, string> $extraTags
     * @return Collection<int, TModelClass>
     */
    public function cachedGet(array $extraTags = [], ?int $ttl = null): Collection
    {
        return Cache::tags($this->resolveTags($extraTags))
            ->remember(
                $this->resolveCacheKey('get'),
                $ttl ?? $this->cacheTtl,
                fn () => $this->get(),
            );
    }

    /**
     * @param array<int, string> $extraTags
     * @return TModelClass|null
     */
    public function cachedFirst(array $extraTags = [], ?int $ttl = null): ?Model
    {
        return Cache::tags($this->resolveTags($extraTags))
            ->remember(
                $this->resolveCacheKey('first'),
                $ttl ?? $this->cacheTtl,
                fn () => $this->first(),
            );
    }

    /** @param array<int, string> $extraTags */
    public function cachedCount(array $extraTags = [], ?int $ttl = null): int
    {
        return (int) Cache::tags($this->resolveTags($extraTags))
            ->remember(
                $this->resolveCacheKey('count'),
                $ttl ?? $this->cacheTtlShort,
                fn () => $this->count(),
            );
    }

    /**
     * @param array<int, string> $extraTags
     * @return LengthAwarePaginator<int, TModelClass>
     */
    public function cachedPaginate(int $perPage = 20, array $extraTags = [], ?int $ttl = null): LengthAwarePaginator
    {
        // Page number is part of the key so page 2 doesn't return page 1's cache
        $key = $this->resolveCacheKey("paginate:{$perPage}:page:".request('page', 1));

        return Cache::tags($this->resolveTags($extraTags))
            ->remember($key, $ttl ?? $this->cacheTtlShort, fn () => $this->paginate($perPage));
    }

    public function clearCache(): static
    {
        if ($this->defaultCacheTags === []) {
            return $this;
        }

        Cache::tags($this->defaultCacheTags)->flush();

        return $this;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function resolveCacheKey(string $suffix): string
    {
        return static::class.':'.md5($this->toRawSql()).':'.$suffix;
    }

    /**
     * @param array<int, string> $extraTags
     * @return array<int, string>
     */
    protected function resolveTags(array $extraTags): array
    {
        return array_unique(array_merge($this->defaultCacheTags, $extraTags));
    }
}
