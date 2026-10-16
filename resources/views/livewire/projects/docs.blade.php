<div class="flex min-h-[calc(100vh-9.5rem)] flex-col md:flex-row">
    {{-- Doc list --}}
    <aside class="shrink-0 border-gray-200 md:w-64 md:border-r">
        <div class="flex items-center justify-between px-4 pb-2 pt-4">
            <p class="text-xs font-semibold text-ink-500">Docs</p>
            @if ($canEdit && ! $editing)
                <button wire:click="$set('showTemplates', true)" class="btn-ghost btn px-2 py-1 text-xs"><x-icon name="plus" /> New</button>
            @endif
        </div>
        <nav class="flex gap-1 overflow-x-auto px-2 pb-3 md:flex-col md:overflow-visible">
            @foreach ($docs as $d)
                <button wire:key="dl-{{ $d->id }}" wire:click="select({{ $d->id }})" @disabled($editing) title="{{ $editing ? 'Click Done to finish editing first' : '' }}"
                        class="group flex shrink-0 items-start gap-2 rounded-md px-2.5 py-2 text-left disabled:cursor-not-allowed disabled:opacity-60 md:w-full {{ $doc && $doc->id === $d->id ? 'bg-accent-50 ring-1 ring-accent-100' : 'hover:bg-gray-50' }}">
                    <x-icon name="list" class="mt-0.5 h-4 w-4 shrink-0 {{ $doc && $doc->id === $d->id ? 'text-accent-500' : 'text-ink-300' }}" />
                    <span class="min-w-0">
                        <span class="block max-w-48 truncate text-sm font-medium text-ink-900">{{ $d->title }}</span>
                        <span class="hidden text-[11px] text-ink-500 md:block">
                            {{ $d->updated_at?->diffForHumans(short: true) }}@if ($d->editor) · {{ $d->editor->firstName() }}@endif
                            @if ($d->client_visible) · <span class="text-emerald-700">Client</span>@endif
                        </span>
                    </span>
                </button>
            @endforeach
        </nav>
    </aside>

    {{-- Doc body --}}
    <section class="min-w-0 flex-1">
        @if (! $doc)
            <div class="mx-auto max-w-3xl px-6 py-10">
                <h2 class="text-lg font-semibold">Write it down where the work happens</h2>
                <p class="mt-1 text-sm text-ink-500">Keep requirements, specs and meeting notes for {{ $project->name }} next to its tasks.@unless ($canEdit) Nothing has been written yet.@endunless</p>
                @if ($canEdit)
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($templates as $key => [$label, $hint])
                            <button wire:click="create('{{ $key }}')" class="card p-4 text-left transition hover:border-accent-400 hover:shadow-sm">
                                <p class="font-medium">{{ $label }}</p><p class="mt-0.5 text-xs text-ink-500">{{ $hint }}</p>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @elseif ($editing)
            {{-- ===== Edit mode ===== --}}
            <div wire:key="editor-{{ $doc->id }}-{{ $editorVersion }}" wire:ignore
                 x-data="tfDocEditor(@js($doc->updated_at?->format('g:i A')))" class="mx-auto max-w-3xl px-6 pb-16 pt-5">
                <div x-cloak x-show="conflict" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    <p><strong x-text="conflict"></strong> saved changes to this doc after you started editing. Your latest edits are not saved yet.</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" x-on:click="save(true)" class="btn bg-amber-600 text-white hover:bg-amber-700">Overwrite with my version</button>
                        <button type="button" x-on:click="tfDialog.confirm('Discard your unsaved changes? The latest saved version will load instead.', { okLabel: 'Discard mine', danger: true }).then(ok => { if (ok) { dirty = false; $wire.reloadLatest() } })" class="btn-secondary btn">Discard mine &amp; load theirs</button>
                    </div>
                </div>

                <div class="mb-2 flex items-center gap-3">
                    <span class="text-xs text-ink-500" x-text="status"></span>
                    <button type="button" x-on:click="done()" class="btn-primary btn ml-auto"><x-icon name="check" /> Done</button>
                </div>
                <input x-ref="title" value="{{ $doc->title }}" maxlength="255" placeholder="Doc title" class="mb-3 w-full border-0 p-0 text-3xl font-semibold text-ink-900 placeholder:text-ink-300 focus:ring-0 focus:outline-none">
                <input id="doc-body-{{ $doc->id }}-{{ $editorVersion }}" x-ref="input" type="hidden" value="{{ $doc->content }}">
                <trix-editor x-ref="trix" input="doc-body-{{ $doc->id }}-{{ $editorVersion }}" class="doc-content" placeholder="Start writing… Paste or drop images anywhere."></trix-editor>
            </div>
        @else
            {{-- ===== View mode ===== --}}
            <div class="mx-auto max-w-3xl px-6 pb-16 pt-5">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <p class="text-xs text-ink-500">
                        Last edited {{ $doc->updated_at?->diffForHumans() }}@if ($doc->editor) by {{ $doc->editor->name }}@endif
                        · {{ number_format($doc->wordCount()) }} {{ \Illuminate\Support\Str::plural('word', $doc->wordCount()) }}
                    </p>
                    <div class="ml-auto flex items-center gap-1.5">
                        @if ($project->client_id)
                            <button @if ($canManage) wire:click="toggleShare" @endif class="pill {{ $doc->client_visible ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-gray-100 text-ink-500' }}" title="{{ $canManage ? 'Click to change' : '' }}">
                                <x-icon :name="$doc->client_visible ? 'eye' : 'eye-off'" class="h-3 w-3" /> {{ $doc->client_visible ? 'Shared with client' : 'Internal' }}
                            </button>
                        @endif
                        <button wire:click="toggleHistory" class="btn-ghost btn text-xs {{ $showHistory ? 'bg-gray-100' : '' }}"><x-icon name="clock" /> History</button>
                        @if ($canEdit)
                            <button wire:click="startEdit" class="btn-primary btn"><x-icon name="list" /> Edit</button>
                            <div class="relative" x-data="{ open: false }">
                                <button x-on:click="open = !open" aria-label="Doc options" :aria-expanded="open" class="btn-ghost btn px-1.5"><x-icon name="dots" class="h-5 w-5" /></button>
                                <div x-cloak x-show="open" x-on:click.outside="open = false" x-on:click="open = false" class="absolute right-0 z-30 mt-1 w-44 rounded-lg bg-white p-1 text-sm shadow-lg ring-1 ring-black/5">
                                    <button wire:click="duplicate" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Duplicate</button>
                                    <button wire:click="move(-1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move up</button>
                                    <button wire:click="move(1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move down</button>
                                    <button x-data x-on:click="window.print()" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Print / save as PDF</button>
                                    @if ($canDelete)
                                        <button wire:click="delete" wire:confirm="Delete “{{ $doc->title }}”? Its history and images are deleted too." class="block w-full rounded px-2 py-1.5 text-left text-red-600 hover:bg-red-50">Delete doc</button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex gap-6">
                    <article class="min-w-0 flex-1">
                        @if ($preview)
                            <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                                Viewing the version from {{ $preview->created_at->format('M j, Y g:i A') }}@if ($preview->user) by {{ $preview->user->name }}@endif.
                                <span class="ml-auto flex gap-2">
                                    <button wire:click="$set('previewRevisionId', null)" class="btn-secondary btn py-1 text-xs">Back to current</button>
                                    @if ($canEdit)<button wire:click="restoreRevision({{ $preview->id }})" wire:confirm="Restore this version? The current version stays in history." class="btn-primary btn py-1 text-xs">Restore this version</button>@endif
                                </span>
                            </div>
                            <h1 class="mb-4 text-3xl font-semibold">{{ $preview->title }}</h1>
                            <div wire:key="rev-{{ $preview->id }}" class="doc-content trix-content" x-data x-init="tfDocImages($el, 'doc-rev-{{ $preview->id }}')">{!! \App\Support\HtmlSanitizer::clean($preview->content) !!}</div>
                        @else
                            <h1 class="mb-4 text-3xl font-semibold">{{ $doc->title }}</h1>
                            @if (trim((string) $doc->content) === '')
                                <p class="text-sm text-ink-500">This doc is empty.@if ($canEdit) <button wire:click="startEdit" class="text-accent-600 hover:underline">Start writing</button>@endif</p>
                            @else
                                <div wire:key="dc-{{ $doc->id }}-{{ $doc->updated_at?->timestamp }}" class="doc-content trix-content" x-data x-init="tfDocImages($el, 'doc-{{ $doc->id }}')">{!! \App\Support\HtmlSanitizer::clean($doc->content) !!}</div>
                            @endif
                        @endif
                    </article>

                    @if ($showHistory)
                        <aside class="w-60 shrink-0 print:hidden">
                            <p class="label">Version history</p>
                            <ul class="space-y-1 text-sm">
                                <li>
                                    <button wire:click="$set('previewRevisionId', null)" class="w-full rounded-md px-2 py-1.5 text-left {{ ! $preview ? 'bg-accent-50 ring-1 ring-accent-100' : 'hover:bg-gray-50' }}">
                                        <span class="block font-medium">Current version</span>
                                        <span class="text-xs text-ink-500">{{ $doc->updated_at?->format('M j, g:i A') }} · {{ $doc->editor?->firstName() }}</span>
                                    </button>
                                </li>
                                @forelse ($revisions as $r)
                                    <li wire:key="rv-{{ $r->id }}">
                                        <button wire:click="previewRevision({{ $r->id }})" class="w-full rounded-md px-2 py-1.5 text-left {{ $preview && $preview->id === $r->id ? 'bg-sky-50 ring-1 ring-sky-200' : 'hover:bg-gray-50' }}">
                                            <span class="block">{{ $r->created_at->format('M j, g:i A') }}</span>
                                            <span class="text-xs text-ink-500">{{ $r->user?->name ?? 'Unknown' }}</span>
                                        </button>
                                    </li>
                                @empty
                                    <li class="px-2 text-xs text-ink-500">Earlier versions appear here after the doc is edited.</li>
                                @endforelse
                            </ul>
                        </aside>
                    @endif
                </div>
            </div>
        @endif
    </section>

    @if ($showTemplates)
        <x-modal title="New doc" close="showTemplates" width="max-w-xl">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($templates as $key => [$label, $hint])
                    <button wire:click="create('{{ $key }}')" class="rounded-lg border border-gray-200 p-4 text-left transition hover:border-accent-400 hover:bg-accent-50/40">
                        <p class="font-medium">{{ $label }}</p><p class="mt-0.5 text-xs text-ink-500">{{ $hint }}</p>
                    </button>
                @endforeach
            </div>
        </x-modal>
    @endif
</div>
