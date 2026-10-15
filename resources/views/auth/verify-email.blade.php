@component('layouts.guest', ['title' => 'Confirm your email'])
    <h1 class="text-2xl font-semibold tracking-tight">Confirm your email</h1>
    <p class="mt-2 text-sm text-ink-700">We sent a link to <strong>{{ auth()->user()->email }}</strong>. Open it to finish setting up your account.</p>
    <p class="mt-2 text-sm text-ink-500">Didn’t get it? Check your spam folder, or send a new link.</p>
    @error('email')<p class="error mt-4">{{ $message }}</p>@enderror
    <div class="mt-6 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn-primary btn py-2">Send a new link</button></form>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn py-2">Sign out</button></form>
    </div>
@endcomponent
