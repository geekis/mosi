<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Emails each active user a daily digest of their unread notifications from the last 24
 * hours. Each digest renders in its own recipient's locale.
 */
#[Signature('app:send-digest')]
#[Description('Send the daily notification digest email to active staff.')]
class SendNotificationDigest extends Command
{
    public function handle(): int
    {
        $sent = 0;

        User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->chunkById(100, function ($users) use (&$sent): void {
                foreach ($users as $user) {
                    $count = $user->unreadNotifications()
                        ->where('created_at', '>=', now()->subDay())
                        ->count();

                    if ($count > 0) {
                        $user->notify(new DailyDigest($count));
                        $sent++;
                    }
                }
            });

        $this->info("Queued {$sent} digest(s).");

        return self::SUCCESS;
    }
}
