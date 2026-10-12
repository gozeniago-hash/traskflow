@component('layouts.guest', ['title' => 'Join '.$invitation->workspace->name])
    @if (! $invitation->isUsable())
        <h1 class="text-2xl font-semibold">This invitation can't be used</h1>
        <p class="mt-2 text-sm text-ink-500">It has expired or was already accepted. Ask {{ $invitation->inviter?->name ?? 'your contact' }} to send a new one.</p>
        <a href="{{ route('login') }}" class="btn-secondary btn mt-6">Go to sign in</a>
    @else
        <h1 class="text-2xl font-semibold">
            @if ($invitation->role === 'client')
                Join the {{ $invitation->workspace->name }} client portal
            @else
                Join {{ $invitation->workspace->name }}
            @endif
        </h1>
        <p class="mt-1 text-sm text-ink-500">
            {{ $invitation->inviter?->name ?? 'Someone' }} invited <strong>{{ $invitation->email }}</strong>
            @if ($invitation->client) on behalf of {{ $invitation->client->name }}@endif
            as {{ \App\Models\Workspace::ROLES[$invitation->role] ?? $invitation->role }}.
        </p>

        <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}" class="mt-6 space-y-4">
            @csrf
            @if ($existingUser)
                <p class="rounded-md bg-gray-100 px-3 py-2 text-sm">You already have a TaskFlow account. Enter your password to join.</p>
                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password" required autofocus class="input">
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary btn w-full py-2">Join workspace</button>
            @else
                <div>
                    <label class="label" for="name">Your name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autofocus class="input">
                    @error('name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">Create a password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="input">
                </div>
                <button class="btn-primary btn w-full py-2">Accept invitation</button>
            @endif
        </form>
    @endif
@endcomponent
