<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Password;

/**
 * Send (or resend) a staff member an invitation to set their password. Resending simply
 * issues a fresh token, which supersedes any previous one for that email.
 */
final class SendStaffInvitation
{
    public function __invoke(User $user): bool
    {
        // Only active accounts with an email can be invited; a record without an email is
        // a data-quality exception to fix, not a login state (.ai/rules/auth.md).
        if (! $user->is_active || blank($user->email)) {
            return false;
        }

        $token = Password::broker('invitations')->createToken($user);

        $user->notify(new StaffInvitation($token));

        return true;
    }
}
