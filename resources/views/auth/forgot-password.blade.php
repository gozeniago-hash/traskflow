@component('layouts.guest', ['title' => 'Reset password'])
    <h1 class="text-2xl font-semibold">Reset your password</h1>
    <p class="mt-1 text-sm text-ink-500">Enter your email and we'll send you a reset link.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary btn w-full py-2">Send reset link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="text-coral-600 hover:underline">Back to sign in</a></p>
@endcomponent
