<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Audit;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::where('email', Str::lower($credentials['email']))->first();
        if (! Auth::validate($credentials) || ! $user) {
            RateLimiter::hit($key);
            if ($user) {
                Audit::log('auth.login_failed', 'Failed sign-in for '.$user->email, $user, [], $user);
            }
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);

        return SignIn::complete($request, $user, $request->boolean('remember'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    /** Sign-up creates a new workspace owned by the new user. */
    public function register(RegisterRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle($request->safe()->only(['name', 'email', 'password', 'workspace']));

        Auth::login($user);
        $request->session()->regenerate();
        Audit::log('auth.registered', $user->name.' created an account and the '.$user->currentWorkspace->name.' workspace', $user, [], $user);

        if ($user->needsToVerifyEmail()) {
            if ($this->sendVerification($user)) {
                return redirect()->route('verification.notice');
            }
            // The confirmation email couldn't be sent: don't lock the new account out.
            $user->markEmailAsVerified();
            Audit::log('auth.verification_skipped', 'Couldn’t send the confirmation email to '.$user->email.', so it was skipped', $user, [], $user);
        }

        return redirect()->route('home')->with('status', 'Welcome to TaskFlow! Create your first project to get started.');
    }

    protected function sendVerification(User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $e) {
            Log::warning('Verification email failed: '.$e->getMessage());

            return false;
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            Audit::log('auth.logout', $request->user()->name.' signed out');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
