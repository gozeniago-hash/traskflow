---
paths:
  - 'app/Models/**'
---

# Models

## Models extend BaseModel
Every model extends `App\Models\BaseModel`, which records each change in the debug log and clears the model's Builder cache. `User` (an `Authenticatable`) uses BaseModel's two traits, `LogsDatabaseChanges` and `ClearsBuilderCache`, directly. The log models `Activity` and `AuditLog` stay on plain `Model`. Hide new secret columns in the model's `$hidden` so they stay out of the log.

## Three logs, three jobs
`Activity::record()` writes the project feed people see in the app. `Audit::log()` writes the admin security audit. The `activity_log` table (spatie/laravel-activitylog) is an automatic debug trail filled by BaseModel; never show it to users and never write to it by hand.

## Backed enums for option sets
New fixed option sets (statuses, priorities, types, roles) are native string-backed enums in `app/Enums` that `use EnumHelpers`, stored in a string column and cast on the model in `casts()`. Define your own `label()` only when the headline of the case name reads wrong. When you change code that uses an existing constant array such as `Task::PRIORITIES`, convert that set to an enum in the same change.

## Filters live in the Builder
Reusable query filters go in the model's Builder (`app/Builders`, see `.ai/rules/builders.md`), not as `scopeX()` methods on the model.

## Every model has a factory
Each model has a factory in `database/factories` with realistic defaults and states for its common variations. Create the factory when you add a model, or the first time a test needs a model that lacks one.
