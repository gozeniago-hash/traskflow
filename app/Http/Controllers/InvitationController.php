<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Audit;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = Invitation::with(['workspace', 'client', 'inviter'])->where('token', $token)->firstOrFail();

        return view('auth.invitation', [
            'invitation' => $invitation,
            'existingUser' => User::where('email', $invitation->email)->first(),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $request->invitation();
        abort_unless($invitation->isUsable(), 410, 'This invitation has expired or was already used.');

        $user = $request->existingUser();
        if ($user && ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is not correct.']);
        }

        $user = $acceptInvitation->handle($invitation, $user, $user ? null : $request->only('name', 'password'));
        abort_unless($user !== null, 410, 'This invitation has expired or was already used.');

        $role = $user->role();
        Audit::log('invitation.accepted', $user->name.' ('.$user->email.') joined as '.$role, $user, [], $user, $invitation->workspace_id);

        return SignIn::complete($request, $user, true, route('dashboard'), 'Welcome to '.$invitation->workspace->name.'!');
    }
}
