---
paths:
  - 'app/Builders/**'
---

# Builders

## One Builder per model
A model with reusable filters gets `App\Builders\<Model>Builder extends BaseBuilder`, with `@extends BaseBuilder<Model>` and attached with `#[UseEloquentBuilder(<Model>Builder::class)]`. Each filter is a public method that only adds constraints, eager loads or ordering and returns `static`. Builders never write. Move a model's `scopeX()` methods into its Builder when you change them, and remove the scope.

## Caching is opt-in
Use plain `get()`/`first()`/`paginate()` by default. Use `cachedGet()`/`cachedFirst()`/`cachedCount()`/`cachedPaginate()` only for slow reads where a short delay is acceptable, never for lists that update live (tasks, boards, comments, notifications). A Builder whose results are cached sets `$defaultCacheTags`. BaseModel clears those tags automatically whenever a model is created, updated or deleted; call `<Model>::query()->clearCache()` yourself only after a bulk query update.
