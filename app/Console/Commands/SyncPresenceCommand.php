<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Microsoft\FetchPresences;
use App\Actions\Presence\ResolvePresence;
use App\Enums\PresenceStatus;
use App\Events\UserPresenceChanged;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Polls Microsoft Graph for the Teams presence of every active Entra user, caches the
 * result for the dot resolver, and broadcasts only the users whose state actually changed
 * since the last tick. Runs every minute from the scheduler — well inside Graph's
 * 1500-requests-per-30-seconds presence budget even for the whole staff directory.
 *
 * Presence webhooks are delegated-only, so polling is the supported path for an app-only
 * daemon; this command is that daemon.
 */
#[Signature('app:sync-presence')]
#[Description('Poll Microsoft Graph for staff presence and broadcast the changes.')]
class SyncPresenceCommand extends Command
{
    /**
     * Keep a synced value alive well past the one-minute poll so the dot resolver still
     * has it if a tick is skipped; a total stall lets it expire back to the heartbeat.
     */
    private const int CACHE_TTL_MINUTES = 10;

    public function __construct(private readonly FetchPresences $presences)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('services.azure.graph.enabled')) {
            $this->warn('Microsoft Graph is disabled; skipping presence sync.');

            return self::SUCCESS;
        }

        $users = User::query()
            ->where('is_active', true)
            ->whereNotNull('entra_oid')
            ->get(['id', 'entra_oid']);

        if ($users->isEmpty()) {
            return self::SUCCESS;
        }

        $byOid = $users->keyBy('entra_oid');
        $entraOids = array_values(array_filter(array_map(
            fn (mixed $oid): string => (string) $oid,
            $users->pluck('entra_oid')->all(),
        )));

        $statuses = ($this->presences)($entraOids);

        $changed = 0;

        foreach ($statuses as $oid => $status) {
            $user = $byOid->get($oid);

            if ($user === null) {
                continue;
            }

            if ($this->store($user->id, $status)) {
                UserPresenceChanged::dispatch($user->id, $status->value);
                $changed++;
            }
        }

        $this->info(sprintf('Presence synced for %d user(s); broadcast %d change(s).', count($statuses), $changed));

        return self::SUCCESS;
    }

    /**
     * Cache the latest status and report whether it differs from what was there before.
     */
    private function store(int $userId, PresenceStatus $status): bool
    {
        $key = ResolvePresence::CACHE_PREFIX.$userId;
        $previous = Cache::get($key);

        Cache::put($key, $status->value, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $previous !== $status->value;
    }
}
