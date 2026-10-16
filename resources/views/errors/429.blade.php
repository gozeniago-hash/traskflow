<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials-head')
    <title>Slow down a little · TaskFlow</title>
</head>
<body class="flex min-h-full items-center justify-center bg-gray-50 px-4">
    <main class="w-full max-w-md rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-200">
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-accent-50 text-accent-600"><x-icon name="clock" class="h-5 w-5" /></span>
        <h1 class="mt-4 text-lg font-semibold">Too many requests</h1>
        <p class="mt-2 text-sm text-ink-500">TaskFlow received a lot of requests from you in a short time. Wait a minute, then try again.</p>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn-primary btn mt-6 inline-flex">Try again</a>
    </main>
</body>
</html>
