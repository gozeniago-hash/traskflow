@component('layouts.guest', ['title' => 'Sign in'])
    <h1 class="text-2xl font-semibold">Welcome back</h1>
    <p class="mt-1 text-sm text-ink-500">Sign in to your workspace or client portal.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label class="label" for="password">Password</label>
                <a href="{{ route('password.request') }}" class="mb-1 text-xs text-coral-600 hover:underline">Forgot?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-700">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Keep me signed in
        </label>
        <button class="btn-primary btn w-full py-2">Sign in</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-500">New to TaskFlow? <a href="{{ route('register') }}" class="font-medium text-coral-600 hover:underline">Create a workspace</a></p>
@endcomponent
