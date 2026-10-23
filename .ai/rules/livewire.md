---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Class-based Livewire components
Write Livewire components as classes in `app/Livewire` with a Blade view in `resources/views/livewire`. Full-page components declare `#[Layout('layouts.app')]` (portal: `layouts.portal`) and `#[Title]`, and are routed with `Route::livewire()`.

## Use InteractsWithWorkspace helpers
Components `use InteractsWithWorkspace` and go through its `me()`, `workspace()`, `toast()` and `requireAdmin()` helpers for the current user, workspace, toasts and admin checks. Real-time listeners come from the `LiveUpdates` concern.

## Authorize and validate every action
Every public method that reads or changes a record starts with `$this->authorize('<ability>', $model)` against the model's Policy (or `requireAdmin()`), even when `mount()` already checked. Validate user input with `$this->validate([...])`, then hand the write to an Action injected into the method (`public function addTask(int $sectionId, CreateTask $createTask): void`), and finish by updating the component's state and calling `toast()`. Mark IDs and other server-set properties `#[Locked]`.
