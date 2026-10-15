<div class="{{ auth()->user()->isClient() ? '' : 'p-6' }}">
    <h1 class="mb-6 text-xl font-semibold">Your profile</h1>
    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <form wire:submit="saveProfile" class="card space-y-4 p-5">
            <div class="flex items-center gap-3">
                <span class="flex h-14 w-14 items-center justify-center rounded-full text-lg font-semibold text-white" style="background-color: {{ \App\Support\Color::forWhiteText($avatar_color) }}">{{ auth()->user()->initials() }}</span>
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

        {{-- Two-step verification --}}
        <section class="card space-y-4 p-5 lg:col-span-2" aria-labelledby="tfa-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="tfa-title" class="flex items-center gap-2 font-semibold">Two-step verification
                        @if ($twoFactorOn)<span class="pill bg-emerald-50 text-emerald-700">On</span>@else<span class="pill bg-gray-100 text-ink-700">Off</span>@endif
                    </h2>
                    <p class="mt-1 max-w-xl text-sm text-ink-500">After your password, TaskFlow asks for a 6-digit code from an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, 1Password, Authy…). Someone who learns your password still can’t get in.</p>
                    @if ($twoFactorRequired && ! $twoFactorOn)<p class="mt-2 text-sm font-medium text-amber-800">Your workspace requires this. Turn it on to keep using TaskFlow.</p>@endif
                </div>
            </div>

            @if ($shownRecoveryCodes)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <h3 class="text-sm font-semibold text-amber-900">Save your recovery codes</h3>
                    <p class="mt-1 text-sm text-amber-900">If you lose your phone, each of these codes lets you sign in once. Keep them somewhere safe, like your password manager. They won’t be shown again.</p>
                    <ul class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-sm sm:grid-cols-5">
                        @foreach ($shownRecoveryCodes as $code)<li>{{ $code }}</li>@endforeach
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js(implode("\n", $shownRecoveryCodes))); $dispatch('toast', { message: 'Recovery codes copied' })" class="btn-secondary btn">Copy codes</button>
                        <button type="button" wire:click="$set('shownRecoveryCodes', null)" class="btn-ghost btn">I’ve saved them</button>
                    </div>
                </div>
            @endif

            @if ($settingUp2fa && $setupSecret)
                <div class="grid gap-5 sm:grid-cols-[auto_1fr]">
                    <div class="flex flex-col items-center gap-2">
                        <canvas x-data x-init="tfQr($el, @js($setupUri))" class="h-44 w-44 rounded-lg ring-1 ring-gray-200" role="img" aria-label="QR code for your authenticator app"></canvas>
                    </div>
                    <form wire:submit="confirmTwoFactor" class="space-y-3">
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-700">
                            <li>Open your authenticator app and add an account.</li>
                            <li>Scan the QR code, or type this key: <span class="select-all break-all rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs">{{ trim(chunk_split($setupSecret, 4, ' ')) }}</span></li>
                            <li>Enter the 6-digit code the app shows.</li>
                        </ol>
                        <div class="max-w-48">
                            <label class="label" for="setup-code">Code</label>
                            <input id="setup-code" wire:model="setupCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="input text-center font-mono tracking-[0.3em]" placeholder="123456">
                            @error('setupCode')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex gap-2">
                            <button class="btn-primary btn">Turn on</button>
                            <button type="button" wire:click="cancelTwoFactor" class="btn-ghost btn">Cancel</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="flex flex-wrap items-end gap-2">
                    <div class="w-full max-w-64">
                        <label class="label" for="tfa-password">Current password</label>
                        <input id="tfa-password" wire:model="twoFactorPassword" type="password" autocomplete="current-password" class="input">
                    </div>
                    @if ($twoFactorOn)
                        <button type="button" wire:click="showRecoveryCodes" class="btn-secondary btn">Show recovery codes ({{ $recoveryLeft }} left)</button>
                        <button type="button" wire:click="regenerateRecoveryCodes" class="btn-secondary btn">New recovery codes</button>
                        @unless ($twoFactorRequired)
                            <button type="button" wire:click="disableTwoFactor" wire:confirm="Turn off two-step verification? Your account will be protected by your password only." data-confirm-label="Turn off" class="btn-ghost btn text-red-700">Turn off</button>
                        @endunless
                    @else
                        <button type="button" wire:click="startTwoFactor" class="btn-primary btn">Set up two-step verification</button>
                    @endif
                </div>
                @error('twoFactorPassword')<p class="error">{{ $message }}</p>@enderror
            @endif
        </section>
    </div>
</div>
