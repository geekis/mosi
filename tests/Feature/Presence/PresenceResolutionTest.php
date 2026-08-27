<?php

declare(strict_types=1);

use App\Actions\Microsoft\FetchPresences;
use App\Actions\Presence\ResolvePresence;
use App\Enums\PresenceStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.azure.tenant', 'test-tenant');
    config()->set('services.azure.client_id', 'client-id');
    config()->set('services.azure.client_secret', 'secret');
    config()->set('services.azure.graph.enabled', true);
    config()->set('services.azure.graph.base_url', 'https://graph.microsoft.com/v1.0');
    Cache::flush();
});

test('graph availability collapses onto the four presentation states', function () {
    expect(PresenceStatus::fromGraphAvailability('Available'))->toBe(PresenceStatus::Available)
        ->and(PresenceStatus::fromGraphAvailability('BeRightBack'))->toBe(PresenceStatus::Away)
        ->and(PresenceStatus::fromGraphAvailability('DoNotDisturb'))->toBe(PresenceStatus::Busy)
        ->and(PresenceStatus::fromGraphAvailability('InAMeeting'))->toBe(PresenceStatus::Busy)
        ->and(PresenceStatus::fromGraphAvailability('PresenceUnknown'))->toBe(PresenceStatus::Offline)
        ->and(PresenceStatus::fromGraphAvailability(null))->toBe(PresenceStatus::Offline);
});

test('each state exposes a colour token', function () {
    expect(PresenceStatus::Available->color())->toBe('green')
        ->and(PresenceStatus::Away->color())->toBe('amber')
        ->and(PresenceStatus::Busy->color())->toBe('red')
        ->and(PresenceStatus::Offline->color())->toBe('zinc');
});

test('fetch presences batches ids over the 650 limit and maps availability', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['value' => [
            ['id' => 'oid-1', 'availability' => 'Busy', 'activity' => 'InAMeeting'],
            ['id' => 'oid-2', 'availability' => 'Available'],
        ]]),
    ]);

    $oids = array_map(fn (int $i): string => "oid-{$i}", range(1, 700));

    $statuses = app(FetchPresences::class)($oids);

    expect($statuses['oid-1'])->toBe(PresenceStatus::Busy)
        ->and($statuses['oid-2'])->toBe(PresenceStatus::Available);

    $presenceCalls = 0;
    Http::assertSent(function ($request) use (&$presenceCalls): bool {
        if (str_contains($request->url(), 'getPresencesByUserId')) {
            $presenceCalls++;
        }

        return true;
    });

    expect($presenceCalls)->toBe(2);
});

test('resolve presence prefers a synced graph value for entra users', function () {
    $user = User::factory()->create(['entra_oid' => 'oid-x']);
    Cache::put(ResolvePresence::CACHE_PREFIX.$user->id, 'busy', 60);

    expect(app(ResolvePresence::class)($user))->toBe(PresenceStatus::Busy);
});

test('resolve presence falls back to the heartbeat when there is no graph value', function () {
    $online = User::factory()->create(['entra_oid' => null, 'last_active_at' => now()]);
    $offline = User::factory()->create(['entra_oid' => null, 'last_active_at' => now()->subHour()]);

    expect(app(ResolvePresence::class)($online))->toBe(PresenceStatus::Available)
        ->and(app(ResolvePresence::class)($offline))->toBe(PresenceStatus::Offline);
});
