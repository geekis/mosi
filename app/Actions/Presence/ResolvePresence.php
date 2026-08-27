<?php

declare(strict_types=1);

namespace App\Actions\Presence;

use App\Enums\PresenceStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The single source of truth for a user's presence, read by both the server-rendered dot
 * and the initial state handed to the frontend store. When Microsoft Graph is enabled and
 * the user is an Entra account with a freshly-synced value, that Teams presence wins;
 * otherwise (Graph off, no `entra_oid`, or no recent sync) it falls back to the in-app
 * `last_active_at` heartbeat — which is all the ~25% of password-only staff ever have.
 */
final class ResolvePresence
{
    public const string CACHE_PREFIX = 'presence:user:';

    public function __invoke(User $user): PresenceStatus
    {
        if ($user->entra_oid !== null && config('services.azure.graph.enabled')) {
            $cached = Cache::get(self::CACHE_PREFIX.$user->id);

            if (is_string($cached) && $cached !== '') {
                return PresenceStatus::tryFrom($cached) ?? PresenceStatus::Offline;
            }
        }

        return $user->isOnline() ? PresenceStatus::Available : PresenceStatus::Offline;
    }
}
