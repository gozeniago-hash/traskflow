@component('layouts.guest', ['title' => 'Choose a new password'])
    <h1 class="text-2xl font-semibold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="input">
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="input">
        </div>
        <button class="btn-primary btn w-full py-2">Reset password</button>
    </form>
@endcomponent
