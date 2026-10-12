@props(['status'])
@if ($status)
    <span {{ $attributes->merge(['class' => 'pill '.(\App\Models\Task::APPROVAL_CLASSES[$status] ?? '')]) }}>{{ \App\Models\Task::APPROVAL[$status] ?? $status }}</span>
@endif
