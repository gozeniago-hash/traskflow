@php
    $me = auth()->user();
    $sidebarProjects = \App\Models\Project::query()->visibleTo($me)->active()
        ->orderByRaw('owner_id = ? desc', [$me->id])->orderBy('name')->limit(12)->get(['id', 'name', 'color']);
    $myWorkspaces = $me->workspaces()->orderBy('name')->get();
    $route = request()->route()?->getName();
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
        <div class="flex items-center justify-between px-3 pt-3" x-data="{ open: false }">
            <button type="button" x-on:click="open = !open" class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-ink-800">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-coral-500 text-xs font-bold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($workspace->name, 0, 1)) }}</span>
                <span class="truncate text-sm font-semibold text-white">{{ $workspace->name }}</span>
                <x-icon name="chevron-down" class="ml-auto h-3.5 w-3.5 text-ink-300" />
            </button>
            <div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute left-3 top-14 z-50 w-56 rounded-lg bg-white p-1.5 shadow-xl ring-1 ring-black/5">
                <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-ink-500">Workspaces</p>
                @foreach ($myWorkspaces as $ws)
                    <form method="POST" action="{{ route('workspaces.switch', $ws) }}">@csrf
                        <button class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-gray-100">
                            <span class="truncate">{{ $ws->name }}</span>
                            @if ($ws->id === $workspace->id)<x-icon name="check" class="h-4 w-4 text-coral-500" />@endif
                        </button>
                    </form>
                @endforeach
                <div class="my-1 border-t border-gray-100"></div>
                <form method="POST" action="{{ route('workspaces.store') }}" class="flex gap-1 p-1">@csrf
                    <input name="name" required placeholder="New workspace…" class="input-sm input text-xs">
                    <button class="btn-secondary btn px-2" title="Create workspace"><x-icon name="plus" /></button>
                </form>
            </div>
        </div>

        <nav class="mt-4 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
            <a href="{{ route('home') }}" wire:navigate class="nav-link {{ $route === 'home' ? 'nav-link-active' : '' }}"><x-icon name="home" /> Home</a>
            <a href="{{ route('my-tasks') }}" wire:navigate class="nav-link {{ $route === 'my-tasks' ? 'nav-link-active' : '' }}"><x-icon name="check-circle" /> My tasks</a>
            <a href="{{ route('projects.index') }}" wire:navigate class="nav-link {{ $route === 'projects.index' ? 'nav-link-active' : '' }}"><x-icon name="folder" /> Projects</a>
            <a href="{{ route('reports') }}" wire:navigate class="nav-link {{ $route === 'reports' ? 'nav-link-active' : '' }}"><x-icon name="chart" /> Reports</a>

            <p class="px-2.5 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Projects</p>
            @forelse ($sidebarProjects as $p)
                <a href="{{ route('projects.show', $p) }}" wire:navigate
                   class="nav-link {{ $currentProjectId === $p->id ? 'nav-link-active' : '' }}">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-[3px]" style="background-color: {{ $p->color }}"></span>
                    <span class="truncate">{{ $p->name }}</span>
                </a>
            @empty
                <p class="px-2.5 text-xs text-ink-500">No projects yet.</p>
            @endforelse

            <p class="px-2.5 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Workspace</p>
            <a href="{{ route('teams') }}" wire:navigate class="nav-link {{ $route === 'teams' ? 'nav-link-active' : '' }}"><x-icon name="users" /> Teams</a>
            <a href="{{ route('clients') }}" wire:navigate class="nav-link {{ $route === 'clients' ? 'nav-link-active' : '' }}"><x-icon name="briefcase" /> Clients</a>
            <a href="{{ route('members') }}" wire:navigate class="nav-link {{ $route === 'members' ? 'nav-link-active' : '' }}"><x-icon name="user" /> Members</a>
            @if ($me->isAdmin())
                <a href="{{ route('settings') }}" wire:navigate class="nav-link {{ $route === 'settings' ? 'nav-link-active' : '' }}"><x-icon name="cog" /> Settings</a>
            @endif
        </nav>

        <div class="border-t border-ink-800 p-3" x-data="{ open: false }">
            <div class="relative">
                <button type="button" x-on:click="open = !open" class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-ink-800">
                    <x-avatar :user="$me" size="sm" />
                    <span class="min-w-0 flex-1 text-left">
                        <span class="block truncate text-sm font-medium text-white">{{ $me->name }}</span>
                        <span class="block truncate text-xs text-ink-300">{{ \App\Models\Workspace::ROLES[$me->role()] ?? '' }}</span>
                    </span>
                </button>
                <div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute bottom-12 left-0 z-50 w-full rounded-lg bg-white p-1.5 shadow-xl ring-1 ring-black/5">
                    <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-100"><x-icon name="user" /> Profile</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-100"><x-icon name="logout" /> Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="print:hidden flex h-12 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4">
            <button type="button" class="btn-ghost btn px-1.5 lg:hidden" x-on:click="nav = true" aria-label="Open menu"><x-icon name="menu" class="h-5 w-5" /></button>
            <form action="{{ route('search') }}" method="GET" class="relative mx-auto w-full max-w-md">
                <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input name="q" value="{{ request('q') }}" placeholder="Search tasks and projects" class="h-8 w-full rounded-full border border-gray-200 bg-gray-50 pl-8 pr-3 text-sm focus:border-coral-400 focus:bg-white focus:ring-2 focus:ring-coral-100 focus:outline-none">
            </form>
            <livewire:timer-bar />
            <a href="{{ route('projects.index', ['new' => 1]) }}" wire:navigate class="btn-primary btn hidden sm:inline-flex"><x-icon name="plus" /> Create</a>
        </header>

        <main class="flex-1 overflow-y-auto bg-white print:overflow-visible">
            {{ $slot }}
        </main>
    </div>
</div>

<livewire:task-panel />
@include('layouts.toasts')
@livewireScripts
</body>
</html>
