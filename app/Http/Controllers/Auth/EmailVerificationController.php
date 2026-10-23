<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->needsToVerifyEmail() ? view('auth.verify-email') : redirect()->route('dashboard');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
            Audit::log('auth.email_verified', $request->user()->name.' confirmed '.$request->user()->email);
        }

        return redirect()->route('dashboard')->with('status', 'Thanks — your email is confirmed.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'We couldn’t send the email just now. Try again in a few minutes.']);
        }

        return back()->with('status', 'A new confirmation link is on its way.');
    }
}
