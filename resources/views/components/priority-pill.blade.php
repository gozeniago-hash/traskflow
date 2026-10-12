@props(['priority'])
@if ($priority && $priority !== 'none')
    <span {{ $attributes->merge(['class' => 'pill '.(\App\Models\Task::PRIORITY_CLASSES[$priority] ?? '')]) }}>{{ \App\Models\Task::PRIORITIES[$priority] }}</span>
@endif
