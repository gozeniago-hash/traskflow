---
paths:
  - 'app/**'
---

# App

## Shared logic goes in App\Support
Put logic shared by several components or controllers in an `App\Support` class with static methods (e.g. `Invites::send()`, `Audit::log()`), and query Eloquent directly from components and controllers. Keep to this altitude: adding Action, Service or Repository layers needs the owner's approval first.

## Clean static analysis, baseline only shrinks
New and changed code must pass `vendor/bin/phpstan analyse` with zero new errors: give every parameter and return a type, and describe arrays with generics or array shapes (`array<int, string>`, `array{name: string}`). Fix the cause of each error. `phpstan-baseline.neon` only ever shrinks; add no baseline entries and no `@phpstan-ignore` comments.

## Dependencies need approval
Add Composer or npm packages only after the owner approves the specific package. Solve the problem with Laravel, Livewire or code already in the app first.
