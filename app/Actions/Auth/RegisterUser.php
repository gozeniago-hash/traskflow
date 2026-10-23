<?php

namespace App\Actions\Auth;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sign-up: a new account and the workspace it owns. Two sign-ups with the same email at
 * the same moment end with one account and a normal "already taken" message for the other.
 */
class RegisterUser
{
    public function __construct(protected CreateWorkspace $createWorkspace) {}

    /**
     * @param array{name: string, email: string, password: string, workspace: string} $data
     *
     * @throws ValidationException when the email is already taken
     */
    public function handle(array $data): User
    {
        try {
            return DB::transaction(function () use ($data): User {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => strtolower(trim($data['email'])),
                    'password' => $data['password'],
                ]);
                $this->createWorkspace->handle($user, $data['workspace']);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }
    }
}
