<div class="p-6">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="btn-primary btn cursor-pointer">
            <x-icon name="upload" /> Upload files
            <input type="file" accept="{{ \App\Rules\SafeUpload::accept() }}" wire:model="uploads" multiple class="hidden">
        </label>
        @if ($project->portal_enabled && $canManage)
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" wire:model="shareWithClient" class="rounded border-gray-300 text-accent-500 focus:ring-accent-400"> Share new uploads with the client
            </label>
        @endif
        @if ($groups->isNotEmpty() || $group !== '')
            <select wire:model.live="group" wire:loading.attr="disabled" wire:target="group" class="input w-auto py-1.5 text-sm sm:ml-auto" aria-label="Filter by group">
                <option value="">All groups</option>
                @foreach ($groups as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
            </select>
        @endif
        <span wire:loading wire:target="uploads" class="text-sm text-ink-500">Uploading…</span>
        @error('uploads.*')<p class="error">{{ $message }}</p>@enderror
    </div>

    @if ($files->isEmpty() && $group !== '')
        <x-empty icon="paperclip" title="No files in {{ $group }}" text="Pick another group, or show all groups." />
    @elseif ($files->isEmpty())
        <x-empty icon="paperclip" title="No files yet" text="Upload briefs, assets and deliverables here, or attach files to individual tasks." />
    @else
        <div class="card overflow-hidden" wire:loading.class="opacity-50 pointer-events-none" wire:target="group">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-ink-500">
                    <tr><th class="px-4 py-2.5 font-semibold">Name</th><th class="hidden px-4 py-2.5 font-semibold md:table-cell">Task</th><th class="hidden px-4 py-2.5 font-semibold sm:table-cell">Uploaded</th><th class="px-4 py-2.5 font-semibold">Client</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($files as $f)
                        <tr wire:key="f-{{ $f->id }}">
                            <td class="px-4 py-2.5">
                                @if ($f->isImage())
                                    <a href="{{ $f->viewUrl() }}" data-lightbox="project-files" data-src="{{ $f->viewUrl() }}" data-download="{{ $f->downloadUrl() }}" data-name="{{ $f->displayName() }}" data-meta="{{ $f->humanSize() }} · {{ $f->user?->name }}" class="flex cursor-zoom-in items-center gap-2.5 hover:underline">
                                        <img src="{{ $f->viewUrl() }}" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded object-cover ring-1 ring-gray-200">
                                        <span class="min-w-0"><span class="block truncate font-medium">{{ $f->displayName() }}</span><span class="text-xs text-ink-500">{{ $f->title ? $f->original_name.' · ' : '' }}{{ $f->humanSize() }}</span></span>
                                    </a>
                                @else
                                    <a href="{{ $f->isPdf() ? $f->viewUrl() : $f->downloadUrl() }}" @if ($f->isPdf()) target="_blank" rel="noopener" @endif class="flex items-center gap-2.5 hover:underline">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded text-[10px] font-semibold {{ $f->isPdf() ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-ink-500' }}">{{ \Illuminate\Support\Str::limit($f->extension(), 4, '') }}</span>
                                        <span class="min-w-0"><span class="block truncate font-medium">{{ $f->displayName() }}</span><span class="text-xs text-ink-500">{{ $f->title ? $f->original_name.' · ' : '' }}{{ $f->humanSize() }}</span></span>
                                    </a>
                                @endif
                                @if ($f->description)<p class="mt-1 max-w-prose text-xs whitespace-pre-line text-ink-700">{{ $f->description }}</p>@endif
                                @if ($f->group || $f->meta)
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-ink-500">
                                        @if ($f->group)<button type="button" wire:click="$set('group', @js($f->group))" class="pill bg-accent-50 text-accent-700" title="Show only {{ $f->group }}"><x-icon name="folder" class="h-3 w-3" /> {{ $f->group }}</button>@endif
                                        @foreach ($f->meta ?? [] as $row)<span><span class="font-medium text-ink-700">{{ $row['label'] }}:</span> {{ $row['value'] }}</span>@endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="hidden px-4 py-2.5 md:table-cell">
                                @if ($f->task)<button wire:click="$dispatch('open-task', { id: {{ $f->task_id }} })" data-peek-title="{{ $f->task?->title }}" class="truncate text-ink-700 hover:underline">{{ $f->task->title }}</button>@if ($f->comment_id)<span class="ml-1 text-xs text-ink-500">(in a comment)</span>@endif @elseif ($f->doc_id) <span class="text-ink-500">In a doc</span>@else <span class="text-ink-500">Project file</span>@endif
                            </td>
                            <td class="hidden px-4 py-2.5 text-ink-500 sm:table-cell">{{ $f->user?->name }} · {{ $f->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-2.5">
                                @if ($f->task_id || $f->doc_id)
                                    @if ($f->isVisibleToClient())<span class="pill bg-emerald-50 text-emerald-700">{{ $f->doc_id ? 'via doc' : 'via task' }}</span>@else<span class="text-xs text-ink-500">Hidden</span>@endif
                                @elseif ($project->portal_enabled && ! $canManage)
                                    <span class="pill {{ $f->client_visible ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-500' }}"><x-icon :name="$f->client_visible ? 'eye' : 'eye-off'" class="h-3 w-3" /> {{ $f->client_visible ? 'Shared' : 'Hidden' }}</span>
                                @elseif ($project->portal_enabled)
                                    <button wire:click="share({{ $f->id }}, {{ $f->client_visible ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="share" class="pill {{ $f->client_visible ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-500' }}">
                                        <x-icon :name="$f->client_visible ? 'eye' : 'eye-off'" class="h-3 w-3" /> {{ $f->client_visible ? 'Shared' : 'Hidden' }}
                                    </button>
                                @else
                                    <span class="text-xs text-ink-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="{{ route('attachments.download', $f) }}" class="btn-ghost btn px-1.5" title="Download"><x-icon name="download" /></a>
                                @can('update', $f)
                                    <button wire:click="edit({{ $f->id }})" wire:loading.attr="disabled" wire:target="edit({{ $f->id }})" class="btn-ghost btn text-xs" title="Edit details">Edit</button>
                                @endcan
                                @can('delete', $f)
                                    <button wire:click="delete({{ $f->id }})" wire:confirm="Delete {{ $f->displayName() }}?" class="btn-ghost btn px-1.5 text-red-600" title="Delete"><x-icon name="trash" /></button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($editingId)
        <x-modal title="File details" close="cancelEdit()">
            <form wire:submit="saveDetails" class="space-y-4" wire:loading.class="opacity-50 pointer-events-none" wire:target="saveDetails, addMetaRow, removeMetaRow">
                <div>
                    <label class="label" for="file-title">Title</label>
                    <input id="file-title" wire:model="form.title" class="input" maxlength="150" placeholder="Uses the file name when left empty" autofocus>
                    @error('form.title')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="file-description">Description</label>
                    <textarea id="file-description" wire:model="form.description" rows="3" class="input" maxlength="1000"></textarea>
                    @error('form.description')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="file-group">Group</label>
                    <input id="file-group" wire:model="form.group" list="file-groups" class="input" maxlength="60" placeholder="e.g. Contracts">
                    <datalist id="file-groups">@foreach ($groups as $g)<option value="{{ $g }}"></option>@endforeach</datalist>
                    @error('form.group')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <p class="label">Extra details</p>
                    <p class="mb-2 text-xs text-ink-500">Only your team sees these.</p>
                    <div class="space-y-2">
                        @foreach ($form['meta'] as $i => $row)
                            <div wire:key="meta-{{ $i }}" class="flex items-start gap-2">
                                <input wire:model="form.meta.{{ $i }}.label" class="input w-2/5" maxlength="60" placeholder="Label" aria-label="Label">
                                <input wire:model="form.meta.{{ $i }}.value" class="input flex-1" maxlength="500" placeholder="Value" aria-label="Value">
                                <button type="button" wire:click="removeMetaRow({{ $i }})" wire:loading.attr="disabled" wire:target="saveDetails, addMetaRow, removeMetaRow" class="btn-ghost btn px-1.5" title="Remove"><x-icon name="x" /></button>
                            </div>
                            @error('form.meta.'.$i.'.label')<p class="error">{{ $message }}</p>@enderror
                            @error('form.meta.'.$i.'.value')<p class="error">{{ $message }}</p>@enderror
                        @endforeach
                    </div>
                    @error('form.meta')<p class="error">{{ $message }}</p>@enderror
                    @if (count($form['meta']) < 20)
                        <button type="button" wire:click="addMetaRow" wire:loading.attr="disabled" wire:target="saveDetails, addMetaRow, removeMetaRow" class="btn-ghost btn mt-2 text-sm"><x-icon name="plus" /> Add a detail</button>
                    @endif
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelEdit" wire:loading.attr="disabled" wire:target="saveDetails" class="btn-secondary btn">Cancel</button>
                    <button class="btn-primary btn" wire:loading.attr="disabled" wire:target="saveDetails">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
