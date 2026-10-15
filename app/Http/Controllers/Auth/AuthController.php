<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
                \App\Support\Audit::log('auth.login_failed', 'Failed sign-in for '.$user->email, $user, [], $user);
            }
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);

        return \App\Support\SignIn::complete($request, $user, $request->boolean('remember'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    /** Sign-up creates a new workspace owned by the new user. */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'workspace' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $workspace = Workspace::create(['name' => $data['workspace'], 'owner_id' => $user->id]);
            $workspace->users()->attach($user->id, ['role' => 'owner']);
            $workspace->teams()->create(['name' => 'General'])->users()->attach($user->id);
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        \App\Support\Audit::log('auth.registered', $user->name.' created an account and the '.$user->currentWorkspace->name.' workspace', $user, [], $user);

        if ($user->needsToVerifyEmail()) {
            $this->sendVerification($user);

            return redirect()->route('verification.notice');
        }

        return redirect()->route('home')->with('status', 'Welcome to TaskFlow! Create your first project to get started.');
    }

    protected function sendVerification(User $user): void
    {
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Verification email failed: '.$e->getMessage());
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            \App\Support\Audit::log('auth.logout', $request->user()->name.' signed out');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
