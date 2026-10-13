@php
    $me = auth()->user();
    $routeProject = request()->route('project');
    $currentProjectId = (int) (is_object($routeProject) ? $routeProject->id : $routeProject);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials-head')
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $workspace->name }} · TaskFlow</title>
</head>
<body class="h-full" x-data="{ nav: false }">
<div class="flex h-full print:block">
    {{-- Mobile backdrop --}}
    <div x-cloak x-show="nav" x-on:click="nav = false" class="fixed inset-0 z-30 bg-ink-950/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside class="print:hidden fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-ink-950 transition-transform lg:static lg:translate-x-0"
           :class="nav && 'translate-x-0'">
        <livewire:sidebar :route="(string) request()->route()?->getName()" :current-project-id="$currentProjectId" />
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="print:hidden flex h-12 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4">
            <button type="button" class="btn-ghost btn px-1.5 lg:hidden" x-on:click="nav = true" aria-label="Open menu"><x-icon name="menu" class="h-5 w-5" /></button>
            <form action="{{ route('search') }}" method="GET" class="relative mx-auto w-full max-w-md">
                <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input name="q" value="{{ request('q') }}" placeholder="Search, or jump to a ticket (e.g. BWR-12)" class="h-8 w-full rounded-full border border-gray-200 bg-gray-50 pl-8 pr-3 text-sm focus:border-coral-400 focus:bg-white focus:ring-2 focus:ring-coral-100 focus:outline-none">
            </form>
            @if (\App\Support\Realtime::enabled())
                <span data-live-indicator data-state="connecting" class="live-indicator hidden items-center gap-1.5 text-xs text-ink-500 sm:inline-flex" title="Live updates">
                    <span class="live-dot h-2 w-2 rounded-full"></span><span class="live-label"></span>
                </span>
            @endif
            <livewire:timer-bar />
            <a href="{{ route('projects.index', ['new' => 1]) }}" wire:navigate class="btn-primary btn hidden sm:inline-flex"><x-icon name="plus" /> Create</a>
        </header>

        <main class="flex-1 overflow-y-auto bg-white print:overflow-visible">
            {{ $slot }}
        </main>
    </div>
</div>

<livewire:task-panel />
<livewire:create-task />
@include('layouts.toasts')
@include('layouts.lightbox')
@livewireScripts
</body>
</html>
