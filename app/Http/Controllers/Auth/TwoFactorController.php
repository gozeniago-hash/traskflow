<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /** How long after the password step the code may be entered. */
    protected const WINDOW_SECONDS = 600;

    public function show(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) ? view('auth.two-factor') : redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in timed out. Please sign in again.']);
        }

        $request->validate(['code' => ['required', 'string', 'max:32']]);

        $key = 'two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! $user->verifyTwoFactorCode($request->input('code'))) {
            RateLimiter::hit($key, 300);
            Audit::log('auth.two_factor_failed', 'Wrong verification code for '.$user->email, $user, [], $user);
            throw ValidationException::withMessages(['code' => 'That code isn’t right. Check your authenticator app and try again.']);
        }

        RateLimiter::clear($key);
        $pending = $request->session()->get(SignIn::PENDING);

        return SignIn::finish($request, $user, (bool) $pending['remember'], $pending['redirect'] ?? null, $pending['status'] ?? null);
    }

    protected function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(SignIn::PENDING);
        if (! $pending || now()->timestamp - $pending['at'] > self::WINDOW_SECONDS) {
            return null;
        }

        return User::find($pending['id']);
    }
}
