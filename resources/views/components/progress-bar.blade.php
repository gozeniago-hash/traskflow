@props(['percent' => 0, 'color' => '#f06a5a'])
<div {{ $attributes->merge(['class' => 'h-1.5 w-full overflow-hidden rounded-full bg-gray-100']) }}>
    <div class="h-full rounded-full transition-all" style="width: {{ $percent }}%; background-color: {{ $color }}"></div>
</div>
