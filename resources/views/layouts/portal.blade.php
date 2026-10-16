@php($me = auth()->user())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials-head')
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $workspace->name }} Client Portal</title>
</head>
<body class="min-h-full bg-gray-50">
<header class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex h-14 max-w-6xl items-center gap-4 px-4">
        <a href="{{ route('portal.dashboard') }}" wire:navigate class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-accent-600 text-xs font-semibold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($workspace->name, 0, 1)) }}</span>
            <span class="text-sm font-semibold">{{ $workspace->name }}</span>
            <span class="hidden rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-ink-500 sm:inline">Client portal</span>
        </a>
        <nav class="ml-4 hidden items-center gap-1 sm:flex">
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="btn-ghost btn {{ request()->routeIs('portal.dashboard', 'portal.projects.show') ? 'bg-gray-100' : '' }}">Projects</a>
            <a href="{{ route('portal.account') }}" wire:navigate class="btn-ghost btn {{ request()->routeIs('portal.account') ? 'bg-gray-100' : '' }}">Account</a>
        </nav>
        <div class="ml-auto flex items-center gap-3" x-data="{ open: false }">
            @if ($me->workspaces()->count() > 1)
                <div class="relative">
                    <button x-on:click="open = !open" class="btn-ghost btn text-xs">Switch <x-icon name="chevron-down" class="h-3 w-3" /></button>
                    <div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-50 mt-1 w-56 rounded-lg bg-white p-1.5 shadow-xl ring-1 ring-black/5">
                        @foreach ($me->workspaces as $ws)
                            <form method="POST" action="{{ route('workspaces.switch', $ws) }}">@csrf
                                <button class="w-full rounded-md px-2 py-1.5 text-left text-sm hover:bg-gray-100">{{ $ws->name }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
            <livewire:notifications.bell />
            <x-avatar :user="$me" size="sm" />
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn-ghost btn px-1.5" title="Log out"><x-icon name="logout" /></button>
            </form>
        </div>
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-8">
    {{ $slot }}
</main>
@include('layouts.toasts')
@include('layouts.lightbox')
@livewireScripts
</body>
</html>
