<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Finishes a sign-in once the password is right: asks for the authenticator code
 * first when the account has two-step verification on.
 */
class SignIn
{
    public const PENDING = 'two_factor.pending';

    public static function complete(Request $request, User $user, bool $remember, ?string $redirect = null, ?string $status = null): RedirectResponse
    {
        if ($user->hasTwoFactor()) {
            $request->session()->put(self::PENDING, [
                'id' => $user->id,
                'remember' => $remember,
                'redirect' => $redirect,
                'status' => $status,
                'at' => now()->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        return static::finish($request, $user, $remember, $redirect, $status);
    }

    public static function finish(Request $request, User $user, bool $remember, ?string $redirect = null, ?string $status = null): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->forget(self::PENDING);
        $request->session()->regenerate();
        Audit::log('auth.login', $user->name.' signed in'.($user->hasTwoFactor() ? ' with two-step verification' : ''), $user, [], $user);

        $response = $redirect ? redirect()->to($redirect) : redirect()->intended(route('dashboard'));

        return $status ? $response->with('status', $status) : $response;
    }
}
