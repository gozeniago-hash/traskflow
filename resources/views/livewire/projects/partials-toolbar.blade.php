{{-- Shared filter toolbar for List/Board views --}}
<div class="flex flex-wrap items-center gap-2 px-6 py-3">
    <button type="button" wire:click="$dispatch('create-task', { projectId: {{ $project->id }} })" class="btn-primary btn"><x-icon name="plus" /> Add task</button>
    <div class="inline-flex rounded-md border border-gray-200 p-0.5 text-xs">
        @foreach (['incomplete' => 'Incomplete', 'all' => 'All', 'completed' => 'Completed'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')" class="rounded px-2.5 py-1 font-medium {{ $filter === $key ? 'bg-ink-900 text-white' : 'text-ink-700 hover:bg-gray-100' }}">{{ $label }}</button>
        @endforeach
    </div>
    <select aria-label="Filter by assignee" wire:model.live="assigneeFilter" class="input input-sm w-auto text-xs">
        <option value="">Everyone's tasks</option>
        <option value="me">Assigned to me</option>
        <option value="unassigned">Unassigned</option>
    </select>
    <span wire:loading class="text-xs text-ink-500">Saving…</span>
</div>
