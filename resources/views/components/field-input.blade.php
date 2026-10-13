{{--
    Input for one custom field, bound with wire:model to $model (e.g. "fieldValues.12").
    live=true saves as you go (task panel); otherwise the value is sent with the form.
--}}
@props(['field', 'model', 'people' => [], 'live' => false, 'disabled' => false])
@php
    $now = $live ? 'wire:model.live' : 'wire:model';
    $blur = $live ? 'wire:model.live.blur' : 'wire:model';
@endphp
@switch($field->type)
    @case('textarea')
        <textarea {{ $blur }}="{{ $model }}" rows="2" @disabled($disabled) class="input input-sm" placeholder="{{ $field->description }}"></textarea>
        @break
    @case('number')
        <input type="number" step="any" {{ $blur }}="{{ $model }}" @disabled($disabled) class="input input-sm w-36" placeholder="0">
        @break
    @case('currency')
        <div class="relative w-40">
            <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-ink-500">{{ config('taskflow.currency_symbol') }}</span>
            <input type="number" step="0.01" {{ $blur }}="{{ $model }}" @disabled($disabled) class="input input-sm pl-6" placeholder="0.00">
        </div>
        @break
    @case('date')
        <input type="date" {{ $now }}="{{ $model }}" @disabled($disabled) class="input input-sm w-40">
        @break
    @case('select')
        <select {{ $now }}="{{ $model }}" @disabled($disabled) class="input input-sm max-w-56">
            <option value="">—</option>
            @foreach ($field->optionList() as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach
        </select>
        @break
    @case('multiselect')
        <div class="flex flex-wrap gap-1.5">
            @foreach ($field->optionList() as $o)
                <label wire:key="{{ $model }}-{{ $o['id'] }}" class="cursor-pointer">
                    <input type="checkbox" value="{{ $o['id'] }}" {{ $now }}="{{ $model }}" @disabled($disabled) class="peer sr-only">
                    <span class="pill ring-1 ring-transparent peer-checked:ring-current peer-focus-visible:ring-2 {{ \App\Models\ProjectField::COLORS[$o['color']] ?? '' }} opacity-50 peer-checked:opacity-100">{{ $o['label'] }}</span>
                </label>
            @endforeach
            @if (! $field->optionList())<span class="text-xs text-ink-500">No options yet.</span>@endif
        </div>
        @break
    @case('checkbox')
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" {{ $now }}="{{ $model }}" @disabled($disabled) class="rounded border-gray-300 text-coral-500 focus:ring-coral-400">
            <span class="text-ink-700">{{ $field->description ?: 'Yes' }}</span>
        </label>
        @break
    @case('url')
        <input type="url" {{ $blur }}="{{ $model }}" @disabled($disabled) class="input input-sm" placeholder="https://">
        @break
    @case('person')
        <select {{ $now }}="{{ $model }}" @disabled($disabled) class="input input-sm max-w-56">
            <option value="">—</option>
            @foreach ($people as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
        </select>
        @break
    @default
        <input type="text" {{ $blur }}="{{ $model }}" maxlength="500" @disabled($disabled) class="input input-sm" placeholder="{{ $field->description }}">
@endswitch
