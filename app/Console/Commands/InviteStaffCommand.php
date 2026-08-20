<?php

namespace App\Console\Commands;

use App\Actions\Staff\SendStaffInvitation;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:invite-staff {email? : Invite (or resend to) one user by email; omit to invite everyone still without a password}')]
#[Description('Send staff invitations to set a password.')]
class InviteStaffCommand extends Command
{
    public function handle(SendStaffInvitation $invite): int
    {
        $query = User::query()->where('is_active', true)->whereNotNull('email');

        if (is_string($email = $this->argument('email'))) {
            $query->where('email', $email);
        } else {
            // Bulk: only those who have not set a password yet.
            $query->whereNull('password');
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No matching users to invite.');

            return self::SUCCESS;
        }

        $sent = $users->filter(fn (User $user): bool => $invite($user))->count();

        $this->info("Sent {$sent} invitation(s).");

        return self::SUCCESS;
    }
}
