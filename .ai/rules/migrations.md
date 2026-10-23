---
paths:
  - 'database/migrations/**'
---

# Migrations

## Migrations are append-only
Change the schema with a new migration (`php artisan make:migration`). Leave migrations that are already committed untouched, because production has already run them. Put demo and sample data in seeders, not migrations.
