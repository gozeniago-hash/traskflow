<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Accepting an invitation: someone with an account confirms their password, someone new
 * picks a name and a password. Anyone holding the link may try, so there is no policy check.
 */
class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->existingUser()) {
            return ['password' => ['required', 'string']];
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function invitation(): Invitation
    {
        return Invitation::where('token', $this->route('token'))->firstOrFail();
    }

    public function existingUser(): ?User
    {
        return User::where('email', $this->invitation()->email)->first();
    }
}
