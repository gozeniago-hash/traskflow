<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        DB::transaction(function () use ($invitation, $user) {
            $invitation->workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role, 'client_id' => $invitation->client_id],
            ]);

            // New internal members join the workspace's first team by default.
            if ($invitation->role !== 'client' && $team = $invitation->workspace->teams()->oldest()->first()) {
                $team->users()->syncWithoutDetaching([$user->id]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();
            $user->forceFill(['current_workspace_id' => $invitation->workspace_id])->save();
        });

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome to '.$invitation->workspace->name.'!');
    }
}
