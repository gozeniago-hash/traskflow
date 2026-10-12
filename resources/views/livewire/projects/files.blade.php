<div class="p-6">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="btn-primary btn cursor-pointer">
            <x-icon name="upload" /> Upload files
            <input type="file" wire:model="uploads" multiple class="hidden">
        </label>
        @if ($project->portal_enabled)
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" wire:model="shareWithClient" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Share new uploads with the client
            </label>
        @endif
        <span wire:loading wire:target="uploads" class="text-sm text-ink-500">Uploading…</span>
        @error('uploads.*')<p class="error">{{ $message }}</p>@enderror
    </div>

    @if ($files->isEmpty())
        <x-empty icon="paperclip" title="No files yet" text="Upload briefs, assets and deliverables here, or attach files to individual tasks." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-ink-500">
                    <tr><th class="px-4 py-2.5 font-semibold">Name</th><th class="hidden px-4 py-2.5 font-semibold md:table-cell">Task</th><th class="hidden px-4 py-2.5 font-semibold sm:table-cell">Uploaded</th><th class="px-4 py-2.5 font-semibold">Client</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($files as $f)
                        <tr wire:key="f-{{ $f->id }}">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('attachments.download', [$f, 'inline' => 1]) }}" target="_blank" class="flex items-center gap-2.5 hover:underline">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-gray-100 text-[10px] font-bold text-ink-500">{{ \Illuminate\Support\Str::limit($f->extension(), 4, '') }}</span>
                                    <span class="min-w-0"><span class="block truncate font-medium">{{ $f->original_name }}</span><span class="text-xs text-ink-500">{{ $f->humanSize() }}</span></span>
                                </a>
                            </td>
                            <td class="hidden px-4 py-2.5 md:table-cell">
                                @if ($f->task)<button wire:click="$dispatch('open-task', { id: {{ $f->task_id }} })" class="truncate text-ink-700 hover:underline">{{ $f->task->title }}</button>@else <span class="text-ink-500">Project file</span>@endif
                            </td>
                            <td class="hidden px-4 py-2.5 text-ink-500 sm:table-cell">{{ $f->user?->name }} · {{ $f->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-2.5">
                                @if ($f->task_id)
                                    @if ($f->isVisibleToClient())<span class="pill bg-emerald-50 text-emerald-700">via task</span>@else<span class="text-xs text-ink-500">Hidden</span>@endif
                                @elseif ($project->portal_enabled)
                                    <button wire:click="toggleShare({{ $f->id }})" class="pill {{ $f->client_visible ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-500' }}">
                                        <x-icon :name="$f->client_visible ? 'eye' : 'eye-off'" class="h-3 w-3" /> {{ $f->client_visible ? 'Shared' : 'Hidden' }}
                                    </button>
                                @else
                                    <span class="text-xs text-ink-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="{{ route('attachments.download', $f) }}" class="btn-ghost btn px-1.5" title="Download"><x-icon name="download" /></a>
                                <button wire:click="delete({{ $f->id }})" wire:confirm="Delete {{ $f->original_name }}?" class="btn-ghost btn px-1.5 text-red-600" title="Delete"><x-icon name="trash" /></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
