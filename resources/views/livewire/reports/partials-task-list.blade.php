<table class="w-full bg-gray-50/60 text-sm">
    <tbody class="divide-y divide-gray-100">
        @foreach ($tasks as $t)
            <tr wire:key="rt-{{ $t->id }}" class="cursor-pointer hover:bg-white" wire:click="$dispatch('open-task', { id: {{ $t->id }} })">
                <td class="py-2 pl-14 pr-3">
                    <span class="block truncate {{ $mode === 'completed' ? 'text-ink-700' : '' }}">{{ $t->title }}</span>
                    @if ($t->parent)<span class="block truncate text-xs text-ink-500">↳ {{ $t->parent->title }}</span>@endif
                </td>
                <td class="hidden w-48 px-3 py-2 sm:table-cell">
                    <span class="flex items-center gap-1.5 truncate text-xs text-ink-700"><span class="h-2 w-2 shrink-0 rounded-sm" style="background-color: {{ $t->project?->color }}"></span>{{ $t->project?->name }}</span>
                </td>
                <td class="hidden w-24 px-3 py-2 md:table-cell"><x-priority-pill :priority="$t->priority" /></td>
                <td class="w-32 px-3 py-2 text-right text-xs">
                    @if ($mode === 'completed')
                        <span class="text-ink-700">Done {{ $t->completed_at->format('M j') }}</span>
                        @if ($t->due_date && $t->completed_at->copy()->startOfDay()->gt($t->due_date))<span class="block text-red-600">due {{ $t->due_date->format('M j') }}</span>@endif
                    @else
                        <span class="{{ $t->dueClass() }}">{{ $t->dueLabel() ?? 'No date' }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
