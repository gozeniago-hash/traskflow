---
paths:
  - 'app/**'
---

# App

## Actions write, Builders read, Support helps
Database writes live in Action classes in `app/Actions` (see `.ai/rules/actions.md`). Reads go through Eloquent, using the model's Builder methods (see `.ai/rules/builders.md`); complex reads go in Query classes in `app/Queries` (see `.ai/rules/queries.md`). `App\Support` holds stateless helpers with static methods (formatting, exports, `Totp`, `HtmlSanitizer`, `Audit::log()`). Move a component's inline write into an Action whenever you change that code. Adding Service or Repository layers needs the owner's approval first.

## Where a read goes
- Inline Builder chain in the component or controller: a read from one model (plus eager loads) used on one screen, e.g. `Task::query()->open()->visibleTo($me)->get()`.
- Builder method: any filter used in more than one place; add it to that model's Builder and call it everywhere.
- Query class in `app/Queries`: a read that joins or aggregates several models (sums, group-bys, report totals), or a complex read needed in more than one place, such as a screen and its export.

These three are the only places reads go; pick by the size of the read, never by habit.

## Reads start from visibility
Every read of projects or tasks starts from a visibility method (`visibleTo`, `partOf`, `managedBy`, `inVisibleProjects`), and every client-portal read also filters to client-visible records. Use the existing method rather than rewriting its conditions.

## Clean static analysis, baseline only shrinks
New and changed code must pass `vendor/bin/phpstan analyse` with zero new errors: give every parameter and return a type, and describe arrays with generics or array shapes (`array<int, string>`, `array{name: string}`). Fix the cause of each error. `phpstan-baseline.neon` only ever shrinks; add no baseline entries and no `@phpstan-ignore` comments.

## Dependencies need approval
Add Composer or npm packages only after the owner approves the specific package. Solve the problem with Laravel, Livewire or code already in the app first.
