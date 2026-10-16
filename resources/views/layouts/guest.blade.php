<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials-head')
    <title>{{ $title ?? 'Sign in' }} · TaskFlow</title>
</head>
<body class="min-h-full bg-gray-50">
<div class="flex min-h-screen">
    {{-- Brand panel (large screens) --}}
    <aside class="relative hidden w-[42%] max-w-xl shrink-0 flex-col overflow-hidden bg-accent-900 px-12 py-10 text-white lg:flex">

        <a href="{{ route('login') }}" class="relative flex items-center gap-2.5 text-lg font-semibold">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-accent-700"><x-icon name="check" class="h-4 w-4" /></span>
            TaskFlow
        </a>

        <div class="relative my-auto py-12">
            <h2 class="text-[2rem] font-semibold leading-tight tracking-tight">
                Plan the work.<br>Share the progress.<br>Keep clients in the loop.
            </h2>
            <ul class="mt-10 space-y-5">
                @foreach ([
                    ['list', 'Lists, boards and calendars', 'Every project, viewed the way your team works.'],
                    ['check-circle', 'My Tasks', 'Everyone sees their priorities across all projects.'],
                    ['briefcase', 'Client portal', 'Share updates, files and approvals with clients.'],
                ] as [$icon, $label, $text])
                    <li class="flex items-start gap-3.5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10">
                            <x-icon :name="$icon" class="h-5 w-5 text-accent-100" />
                        </span>
                        <span>
                            <span class="block text-sm font-medium text-white">{{ $label }}</span>
                            <span class="block text-sm text-accent-100/80">{{ $text }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="relative text-xs text-white/60">&copy; {{ date('Y') }} TaskFlow</p>
    </aside>

    {{-- Form --}}
    <main class="flex flex-1 flex-col items-center justify-center px-5 py-10 sm:px-8">
        <div class="w-full max-w-sm">
            {{-- Logo for small screens, where the brand panel is hidden --}}
            <a href="{{ route('login') }}" class="mb-8 flex items-center gap-2 text-base font-semibold text-ink-900 lg:hidden">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent-600 text-white"><x-icon name="check" class="h-4 w-4" /></span>
                TaskFlow
            </a>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                @if (session('status'))
                    <div class="mb-5 flex items-start gap-2 rounded-md bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800 ring-1 ring-emerald-200">
                        <x-icon name="check-circle" class="mt-0.5 h-4 w-4 text-emerald-700" />
                        <span>{{ session('status') }}</span>
                    </div>
                @endif
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="mt-6 text-center text-sm text-ink-500">{{ $footer }}</div>
            @endisset
        </div>
    </main>
</div>
@livewireScripts
</body>
</html>
