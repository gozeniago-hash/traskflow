@props(['user' => null, 'size' => 'md'])
@php
    $sizes = ['xs' => 'h-5 w-5 text-[9px]', 'sm' => 'h-6 w-6 text-[10px]', 'md' => 'h-8 w-8 text-xs', 'lg' => 'h-10 w-10 text-sm', 'xl' => 'h-14 w-14 text-lg'];
@endphp
@if ($user)
    <span title="{{ $user->name }}" role="img" aria-label="{{ $user->name }}" {{ $attributes->merge(['class' => 'inline-flex shrink-0 select-none items-center justify-center rounded-full font-semibold text-white '.$sizes[$size]]) }} style="background-color: {{ \App\Support\Color::forWhiteText($user->avatar_color ?? '#6d6e6f') }}">{{ $user->initials() }}</span>
@else
    <span title="Unassigned" role="img" aria-label="Unassigned" {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-400 '.$sizes[$size]]) }}><x-icon name="user" class="h-3/5 w-3/5" /></span>
@endif
