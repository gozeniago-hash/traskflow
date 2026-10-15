@component('layouts.guest', ['title' => 'Sign in'])
    <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
    <p class="mt-1 text-sm text-ink-500">Sign in to your workspace or client portal.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" x-data="{ busy: false }" x-on:submit="busy = true">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email"
                   placeholder="you@company.com" class="input @error('email') border-red-400 ring-2 ring-red-100 @enderror"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p id="email-error" class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="mb-1 flex items-center justify-between">
                <label class="label mb-0" for="password">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-medium text-coral-700 hover:underline">Forgot password?</a>
            </div>
            <x-password-input />
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-700">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Keep me signed in
        </label>
        <button class="btn-primary btn w-full py-2.5" :disabled="busy">
            <span x-show="!busy">Sign in</span>
            <span x-show="busy" x-cloak>Signing in…</span>
        </button>
    </form>

    @slot('footer')
        New to TaskFlow? <a href="{{ route('register') }}" class="font-medium text-coral-700 hover:underline">Create a workspace</a>
    @endslot
@endcomponent
