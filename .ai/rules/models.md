---
paths:
  - 'app/Models/**'
---

# Models

## Backed enums for option sets
New fixed option sets (statuses, priorities, types, roles) are string-backed enums in `app/Enums` with a `label(): string` method, stored in a string column and cast on the model in `casts()`. When you change code that uses an existing constant array such as `Task::PRIORITIES`, convert that set to an enum in the same change.

## Every model has a factory
Each model has a factory in `database/factories` with realistic defaults and states for its common variations. Create the factory when you add a model, or the first time a test needs a model that lacks one.
