@component('layouts.guest', ['title' => 'Two-step verification'])
    <div x-data="{ recovery: @js($errors->has('code') && strlen(old('code', '')) > 8) }">
        <h1 class="text-2xl font-semibold tracking-tight">Two-step verification</h1>
        <p class="mt-1 text-sm text-ink-500" x-show="!recovery">Enter the 6-digit code from your authenticator app.</p>
        <p class="mt-1 text-sm text-ink-500" x-show="recovery" x-cloak>Enter one of the recovery codes you saved when you turned this on. Each code works once.</p>
        <form method="POST" action="{{ route('two-factor.challenge') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label" for="code" x-text="recovery ? 'Recovery code' : 'Verification code'">Verification code</label>
                <input id="code" name="code" required autofocus autocomplete="one-time-code" class="input text-center font-mono text-lg tracking-[0.3em]"
                       :inputmode="recovery ? 'text' : 'numeric'" :maxlength="recovery ? 11 : 6" :placeholder="recovery ? 'xxxxx-xxxxx' : '123456'" inputmode="numeric" maxlength="6">
                @error('code')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary btn w-full py-2">Verify and sign in</button>
        </form>
        <button type="button" x-on:click="recovery = !recovery; $nextTick(() => document.getElementById('code').focus())" class="mt-4 text-sm font-medium text-accent-700 hover:underline"
                x-text="recovery ? 'Use my authenticator app instead' : 'Lost your phone? Use a recovery code'">Lost your phone? Use a recovery code</button>
    </div>
    @slot('footer')
        <a href="{{ route('login') }}" class="font-medium text-accent-700 hover:underline">← Back to sign in</a>
    @endslot
@endcomponent
