---
paths:
  - 'app/Http/**'
---

# Http

## Form Requests in controllers
Controller actions that accept input take a Form Request (`php artisan make:request`) whose `authorize()` calls the model's Policy and whose `rules()` returns array rules. Convert an action's inline `$request->validate()` to a Form Request whenever you change that action. Controllers stay thin: authorize, validate, call a model or Support method, return a response.
