---
paths:
  - 'resources/views/**'
  - 'resources/js/**'
---

# Front end

## Controls lock while their request runs
While a request runs, lock the control that sent it (a `wire:click` or `wire:submit` action, a `wire:model.live` change, an upload, a drag-to-reorder, or a `fetch` in Alpine or `app.js`) and every element the request directly affects: the form's other inputs and buttons, the row, card or list being changed or removed, and any other control that would act on the same record. Keep the lock until the response arrives, so nothing can be sent twice or edited mid-save. On Livewire elements add `wire:loading.attr="disabled"` to inputs and buttons, and `wire:loading.class="opacity-50 pointer-events-none"` to affected containers, each with `wire:target` naming the action and any upload property it waits on, as in `create-task.blade.php`. In Alpine or JS, set a `busy` flag before the request, bind `:disabled="busy"` (or the same classes) on each affected element, and clear it in `finally`.
After the response, those elements show the state the server decided, not the state they had before the request: render `@disabled(...)`, values, labels and `aria-label` from component state (e.g. `$canEdit`, `isCompleted()`). A failed action leaves them editable to try again with the user's input kept; a finished one shows the new state, such as a button switching to "Mark incomplete", a removed row disappearing, or a control staying disabled when there is nothing left to do.
