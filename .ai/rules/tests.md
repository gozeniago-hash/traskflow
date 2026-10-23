---
paths:
  - 'tests/**'
---

# Tests

## Tests ship with every change
Every behaviour change or bug fix ships with a PHPUnit feature test (`php artisan make:test --phpunit`); a bug fix starts with a test that fails on the bug. Cover the forbidden path too: a user without access gets a 403 or no change. Build the records a new test needs with model factories; seed `DemoSeeder` only in existing tests that already rely on it.
