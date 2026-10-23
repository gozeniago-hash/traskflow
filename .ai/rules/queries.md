---
paths:
  - 'app/Queries/**'
---

# Queries

## One Query class per complex read
`app/Queries/<Area>/<Name>.php`, named after what it answers (e.g. `Reports/WorkloadByAssignee`). It takes its inputs (the viewing user, filters, date range) in the constructor and exposes one public method: `get()` for finished results, or `builder(): Builder` when the caller still needs to paginate or chain. Build it from the models' Builder methods and start from the viewer's visibility method. Queries only read. Before writing a new one, look for an existing Query that answers the same question.

## Test every Query
Each Query has a feature test that checks its results, and that a user without access to a project gets none of that project's rows.
