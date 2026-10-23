---
paths:
  - 'app/Enums/**'
---

# Enums

## Native enums with EnumHelpers
Every enum is a native string-backed enum (`enum TaskPriority: string`) with TitleCase cases and snake_case values (`case InProgress = 'in_progress';`), and it `use`s `App\Enums\Concerns\EnumHelpers` for `label()`, `options()`, `getSelectDropdownData()` and `random()`. Define your own `label()` only when the headline of the case name reads wrong. Store it in a `string()` column and cast it on the model; validate input with `Rule::enum()`.
