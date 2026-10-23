<?php

namespace App\Livewire;

use App\Actions\Profile\UpdateProfile;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\User;
use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile')]
class Profile extends Component
{
    use InteractsWithWorkspace;

    public string $name = '';

    public string $email = '';

    public string $title = '';

    public string $avatar_color = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Two-step verification
    public bool $settingUp2fa = false;

    public string $setupCode = '';

    public string $twoFactorPassword = '';

    public ?array $shownRecoveryCodes = null;

    public function mount(): void
    {
        $me = $this->me();
        $this->name = $me->name;
        $this->email = $me->email;
        $this->title = (string) $me->title;
        $this->avatar_color = (string) $me->avatar_color;
    }

    public function saveProfile(UpdateProfile $updateProfile): void
    {
        $this->email = strtolower(trim($this->email));
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->me()->id)],
            'title' => ['nullable', 'string', 'max:120'],
            'avatar_color' => ['required', Rule::in(User::AVATAR_COLORS)],
        ]);

        if ($updateProfile->handle($this->me(), $data)) {
            $this->redirectRoute('verification.notice');

            return;
        }
        $this->dispatch('profile-updated');
        $this->toast('Profile saved.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $this->me()->update(['password' => Hash::make($this->password)]);
        $this->reset(['current_password', 'password', 'password_confirmation']);
        Audit::log('auth.password_changed', $this->me()->name.' changed their password', $this->me());
        $this->toast('Password updated.');
    }

    // ---- two-step verification ------------------------------------------------

    public function startTwoFactor(): void
    {
        $this->validate(['twoFactorPassword' => ['required', 'current_password']], [], ['twoFactorPassword' => 'password']);
        session()->put('two_factor.setup', Totp::generateSecret());
        $this->reset(['twoFactorPassword', 'setupCode']);
        $this->settingUp2fa = true;
    }

    public function cancelTwoFactor(): void
    {
        session()->forget('two_factor.setup');
        $this->reset(['settingUp2fa', 'setupCode']);
    }

    public function confirmTwoFactor(): void
    {
        $secret = session('two_factor.setup');
        abort_unless($secret, 419);
        $this->validate(['setupCode' => ['required', 'string']], [], ['setupCode' => 'code']);

        $step = Totp::verify($secret, $this->setupCode);
        if ($step === null) {
            $this->addError('setupCode', 'That code didn’t match. Make sure your phone’s clock is set automatically, then try the newest code.');

            return;
        }

        $codes = User::newRecoveryCodes();
        $this->me()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
        ])->save();
        session()->forget('two_factor.setup');
        Audit::log('auth.two_factor_enabled', $this->me()->name.' turned on two-step verification', $this->me());

        $this->reset(['settingUp2fa', 'setupCode']);
        $this->shownRecoveryCodes = $codes;
        $this->toast('Two-step verification is on.');
    }

    public function showRecoveryCodes(): void
    {
        $this->validate(['twoFactorPassword' => ['required', 'current_password']], [], ['twoFactorPassword' => 'password']);
        $this->reset('twoFactorPassword');
        $this->shownRecoveryCodes = $this->me()->two_factor_recovery_codes ?? [];
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->validate(['twoFactorPassword' => ['required', 'current_password']], [], ['twoFactorPassword' => 'password']);
        $codes = User::newRecoveryCodes();
        $this->me()->forceFill(['two_factor_recovery_codes' => $codes])->save();
        Audit::log('auth.recovery_codes_regenerated', $this->me()->name.' made new recovery codes', $this->me());
        $this->reset('twoFactorPassword');
        $this->shownRecoveryCodes = $codes;
        $this->toast('New recovery codes made. The old ones no longer work.');
    }

    public function disableTwoFactor(): void
    {
        $me = $this->me();
        if ($me->currentWorkspace?->require_two_factor && ! $me->isClient()) {
            $this->addError('twoFactorPassword', 'Your workspace requires two-step verification, so it can’t be turned off.');

            return;
        }
        $this->validate(['twoFactorPassword' => ['required', 'current_password']], [], ['twoFactorPassword' => 'password']);
        $me->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        Audit::log('auth.two_factor_disabled', $me->name.' turned off two-step verification', $me);
        $this->reset(['twoFactorPassword', 'shownRecoveryCodes']);
        $this->toast('Two-step verification is off.');
    }

    public function render()
    {
        $secret = $this->settingUp2fa ? session('two_factor.setup') : null;

        return view('livewire.profile', [
            'colors' => User::AVATAR_COLORS,
            'twoFactorOn' => $this->me()->hasTwoFactor(),
            'twoFactorRequired' => (bool) $this->me()->currentWorkspace?->require_two_factor && ! $this->me()->isClient(),
            'setupSecret' => $secret,
            'setupUri' => $secret ? Totp::uri($secret, $this->me()->email) : null,
            'recoveryLeft' => count($this->me()->two_factor_recovery_codes ?? []),
        ])
            ->layout($this->me()->isClient() ? 'layouts.portal' : 'layouts.app');
    }
}
