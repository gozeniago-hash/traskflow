<div>
@if ($show && $project)
    <x-modal title="New task" close="show" width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="flex items-center gap-2 text-xs text-ink-500">
                <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $project->color }}"></span>{{ $project->name }}
            </div>
            <div>
                <input wire:model="title" placeholder="Task name" class="input text-base font-medium" autofocus>
                @error('title')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <textarea wire:model="description" rows="4" placeholder="Add details, links, acceptance criteria…" class="input"></textarea>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Section</label>
                    <select aria-label="Section" wire:model="section_id" class="input input-sm">
                        @foreach ($project->sections as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Assignee</label>
                    <select aria-label="Assignee" wire:model="assignee_id" class="input input-sm">
                        <option value="">No assignee</option>
                        @foreach ($people as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->id === auth()->id() ? ' (me)' : '' }}</option>@endforeach
                    </select>
                    @error('assignee_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Due date</label>
                    <input type="date" wire:model="due_date" class="input input-sm">
                </div>
                <div>
                    <label class="label">Priority</label>
                    <div class="flex gap-1">
                        @foreach (\App\Models\Task::PRIORITIES as $k => $label)
                            <button type="button" wire:click="$set('priority', '{{ $k }}')" class="pill {{ $priority === $k ? ($k === 'none' ? 'bg-ink-900 text-white' : \App\Models\Task::PRIORITY_CLASSES[$k].' ring-1 ring-current') : 'bg-gray-100 text-ink-500 hover:bg-gray-200' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($fields->isNotEmpty())
                <div class="grid gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-2">
                    @foreach ($fields as $f)
                        <div wire:key="cf-{{ $f->id }}" class="{{ in_array($f->type, ['textarea', 'multiselect'], true) ? 'sm:col-span-2' : '' }}">
                            <label class="label">{{ $f->name }}@if ($f->required)<span class="text-red-600"> *</span>@endif</label>
                            <x-field-input :field="$f" model="fieldValues.{{ $f->id }}" :people="$people->pluck('name', 'id')->all()" />
                            @error('fieldValues.'.$f->id)<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Attachments --}}
            <div x-data="{ drag: false }"
                 x-on:dragover.prevent="drag = true" x-on:dragleave.prevent="drag = false"
                 x-on:drop.prevent="drag = false; $refs.picker.files = $event.dataTransfer.files; $refs.picker.dispatchEvent(new Event('change'))"
                 class="rounded-lg border-2 border-dashed p-4 text-center transition" :class="drag ? 'border-coral-400 bg-coral-50' : 'border-gray-200'">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                    <x-icon name="paperclip" class="h-4 w-4" />
                    <span><span class="font-medium text-coral-600 hover:underline">Attach files or images</span> or drag them here</span>
                    <input type="file" accept="{{ \App\Rules\SafeUpload::accept() }}" x-ref="picker" wire:model="newFiles" multiple class="hidden">
                </label>
                <p wire:loading wire:target="newFiles" class="mt-1 text-xs text-ink-500">Uploading…</p>
                @error('newFiles.*')<p class="error">{{ $message }}</p>@enderror
                @error('files.*')<p class="error">{{ $message }}</p>@enderror
                <x-upload-queue :files="$files" remove="removeFile" class="mt-3 justify-center" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-4 text-sm text-ink-700">
                    @if ($project->portal_enabled)
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="client_visible" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Visible to client</label>
                    @endif
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="openAfter" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Open task after creating</label>
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="$set('show', false)" class="btn-secondary btn">Cancel</button>
                    <button class="btn-primary btn" wire:loading.attr="disabled" wire:target="save,newFiles">Create task</button>
                </div>
            </div>
        </form>
    </x-modal>
@endif
</div>
