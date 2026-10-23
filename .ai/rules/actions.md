---
paths:
  - 'app/Actions/**'
---

# Actions

## Persistence lives in Actions
Every database write (create, update, delete, attach, reorder) and its side effects (`Activity::record`, `Audit::log`, `Realtime::touch`, mail) lives in an Action class: `app/Actions/<Area>/<Verb><Noun>.php` (e.g. `Tasks/CreateTask`, `Comments/AddComment`) with one public `handle()` method taking typed models and values and returning the result. Before writing a new one, look for an existing Action for that operation and reuse it. Actions trust their caller: authorization and input validation happen before `handle()` is called.

## Transactions for multiple writes
When `handle()` makes more than one write, wrap them in `DB::transaction(function () { ... })` so they all succeed or none do. Read values you then write back (positions, counters, sequence numbers) with `->lockForUpdate()` inside that transaction. Send mail and broadcasts only after the commit (`DB::afterCommit()`).

## Save models, bulk-update only for reorders
Write through models (`create()`, `update()`, `save()`, `delete()`) so the debug log and cache clearing run. Bulk query updates (`->whereKey($ids)->update([...])`) skip both; use them only for reorders and counters, and call `<Model>::query()->clearCache()` afterwards when that model caches.

## Test every Action
Each Action has a feature test that calls `handle()` directly and asserts the database rows and side effects it produces, including the all-or-nothing behaviour of multi-write Actions.
