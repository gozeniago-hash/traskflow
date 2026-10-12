<div class="{{ auth()->user()->isClient() ? '' : 'p-6' }}">
    <h1 class="mb-6 text-xl font-semibold">Your profile</h1>
    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <form wire:submit="saveProfile" class="card space-y-4 p-5">
            <div class="flex items-center gap-3">
                <span class="flex h-14 w-14 items-center justify-center rounded-full text-lg font-semibold text-white" style="background-color: {{ $avatar_color }}">{{ auth()->user()->initials() }}</span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($colors as $c)
                        <button type="button" wire:click="$set('avatar_color', '{{ $c }}')" class="h-6 w-6 rounded-full ring-offset-2 {{ $avatar_color === $c ? 'ring-2 ring-ink-900' : '' }}" style="background-color: {{ $c }}" aria-label="Color {{ $c }}"></button>
                    @endforeach
                </div>
            </div>
            <div><label class="label">Name</label><input wire:model="name" class="input">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Email</label><input wire:model="email" type="email" class="input">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Job title</label><input wire:model="title" class="input" placeholder="e.g. Project Manager"></div>
            <div class="flex justify-end"><button class="btn-primary btn">Save profile</button></div>
        </form>

        <form wire:submit="savePassword" class="card h-fit space-y-4 p-5">
            <h2 class="font-semibold">Change password</h2>
            <div><label class="label">Current password</label><input wire:model="current_password" type="password" class="input">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">New password</label><input wire:model="password" type="password" class="input">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Confirm new password</label><input wire:model="password_confirmation" type="password" class="input"></div>
            <div class="flex justify-end"><button class="btn-primary btn">Update password</button></div>
        </form>
    </div>
</div>
