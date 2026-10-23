<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Audit;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();
        abort_unless($invitation->isUsable(), 410, 'This invitation has expired or was already used.');

        $user = User::where('email', $invitation->email)->first();

        if ($user) {
            $request->validate(['password' => ['required', 'string']]);
            if (! Hash::check($request->input('password'), $user->password)) {
                throw ValidationException::withMessages(['password' => 'That password is not correct.']);
            }
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);
            $user = User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
        }

        // The invitation link went to this address, so it counts as verified.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role, 'client_id' => $invitation->client_id],
            ]);

            // Internal members join only the teams the admin picked when inviting.
            if ($invitation->role !== 'client' && $invitation->team_ids) {
                foreach ($invitation->workspace->teams()->whereKey($invitation->team_ids)->get() as $team) {
                    $team->users()->syncWithoutDetaching([$user->id]);
                }
            }

            $invitation->forceFill(['accepted_at' => now()])->save();
            $user->forceFill(['current_workspace_id' => $invitation->workspace_id])->save();
        });

        Audit::log('invitation.accepted', $user->name.' ('.$user->email.') joined as '.$invitation->role, $user, [], $user, $invitation->workspace_id);

        return SignIn::complete($request, $user, true, route('dashboard'), 'Welcome to '.$invitation->workspace->name.'!');
    }
}
