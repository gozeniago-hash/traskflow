{{-- Pending uploads (Livewire temporary files) with a remove button. --}}
@props(['files' => [], 'remove'])
@if (count($files))
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
        @foreach ($files as $i => $file)
            <div wire:key="uq-{{ $remove }}-{{ $i }}-{{ $file->getFilename() }}" class="group relative flex items-center gap-2 rounded-md border border-gray-200 bg-white p-1.5 pr-7 text-xs">
                @if ($file->isPreviewable())
                    <img src="{{ $file->temporaryUrl() }}" alt="" class="h-9 w-9 rounded object-cover">
                @else
                    <span class="flex h-9 w-9 items-center justify-center rounded bg-gray-100 text-[10px] font-bold text-ink-500">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($file->getClientOriginalExtension() ?: 'file', 4, '')) }}</span>
                @endif
                <span class="max-w-36 truncate">{{ $file->getClientOriginalName() }}</span>
                <button type="button" wire:click="{{ $remove }}({{ $i }})" class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-ink-500 hover:bg-gray-100 hover:text-red-600" title="Remove"><x-icon name="x" class="h-3.5 w-3.5" /></button>
            </div>
        @endforeach
    </div>
@endif
