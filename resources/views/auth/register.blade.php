@component('layouts.guest', ['title' => 'Create your workspace'])
    <h1 class="text-2xl font-semibold tracking-tight">Create your workspace</h1>
    <p class="mt-1 text-sm text-ink-500">You'll be the owner. Invite your team and clients next.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="name">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus class="input">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="email">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="input">
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="workspace">Company or workspace name</label>
            <input id="workspace" name="workspace" value="{{ old('workspace') }}" required placeholder="e.g. Acme Studio" class="input">
            @error('workspace')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <x-password-input autocomplete="new-password" />
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirm password</label>
            <x-password-input name="password_confirmation" autocomplete="new-password" />
        </div>
        <button class="btn-primary btn w-full py-2">Create workspace</button>
    </form>

    @slot('footer')
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-coral-700 hover:underline">Sign in</a>
    @endslot
@endcomponent
