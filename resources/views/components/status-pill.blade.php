@props(['status'])
<span {{ $attributes->merge(['class' => 'pill '.(\App\Models\Project::STATUS_CLASSES[$status] ?? 'bg-gray-100 text-gray-700')]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ \App\Models\Project::STATUS_DOTS[$status] ?? 'bg-gray-400' }}"></span>
    {{ \App\Models\Project::STATUSES[$status] ?? $status }}
</span>
