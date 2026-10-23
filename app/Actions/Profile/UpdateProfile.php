<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a person's name, email, title and colour. A new email is stored in lowercase and,
 * when the app sends email, has to be confirmed again.
 */
class UpdateProfile
{
    /**
     * @param array{name: string, email: string, title?: string|null, avatar_color: string} $data
     * @return bool true when the new email now needs confirming
     *
     * @throws ValidationException when someone else took the email at the same moment
     */
    public function handle(User $user, array $data): bool
    {
        $data['email'] = strtolower(trim($data['email']));
        $emailChanged = $data['email'] !== strtolower($user->email);

        try {
            $user->update($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        if (! $emailChanged) {
            return false;
        }

        Audit::log('auth.email_changed', $user->name.' changed their email to '.$user->email, $user);
        if (! config('taskflow.verify_email')) {
            return false;
        }

        $user->forceFill(['email_verified_at' => null])->save();
        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }
}
