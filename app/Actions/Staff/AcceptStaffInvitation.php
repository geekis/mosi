<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Accept an invitation: verify the single-use, expiring token and set the password.
 * Returns the broker status and, on success, the user (so the caller can log them in).
 */
final class AcceptStaffInvitation
{
    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     * @return array{0: string, 1: User|null}
     */
    public function __invoke(array $credentials): array
    {
        $accepted = null;

        $status = Password::broker('invitations')->reset(
            $credentials,
            function (User $user, string $password) use (&$accepted): void {
                $user->forceFill([
                    'password' => $password, // the model's hashed cast hashes it on save
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $accepted = $user;
            },
        );

        return [$status, $accepted];
    }
}
