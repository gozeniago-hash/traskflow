<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials-head')
    <title>{{ $title ?? 'Sign in' }} · TaskFlow</title>
</head>
<body class="min-h-full bg-gray-50">
<div class="flex min-h-screen">
    <div class="hidden w-[42%] flex-col justify-between bg-ink-950 p-10 text-white lg:flex">
        <div class="flex items-center gap-2 text-lg font-semibold">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-coral-500"><x-icon name="check" class="h-4 w-4" /></span>
            TaskFlow
        </div>
        <div>
            <p class="text-3xl font-semibold leading-tight">Plan the work.<br>Share the progress.<br><span class="text-coral-400">Keep clients in the loop.</span></p>
            <ul class="mt-8 space-y-3 text-sm text-ink-300">
                <li class="flex items-center gap-2"><x-icon name="list" class="text-coral-400" /> Lists, boards and calendars for every project</li>
                <li class="flex items-center gap-2"><x-icon name="check-circle" class="text-coral-400" /> My Tasks keeps everyone's priorities straight</li>
                <li class="flex items-center gap-2"><x-icon name="briefcase" class="text-coral-400" /> A client portal for updates, files and approvals</li>
            </ul>
        </div>
        <p class="text-xs text-ink-500">&copy; {{ date('Y') }} TaskFlow</p>
    </div>
    <div class="flex flex-1 items-center justify-center p-6">
        <div class="w-full max-w-sm">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
