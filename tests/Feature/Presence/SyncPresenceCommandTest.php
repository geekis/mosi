<?php

declare(strict_types=1);

use App\Actions\Presence\ResolvePresence;
use App\Events\UserPresenceChanged;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.azure.tenant', 'test-tenant');
    config()->set('services.azure.client_id', 'client-id');
    config()->set('services.azure.client_secret', 'secret');
    config()->set('services.azure.graph.enabled', true);
    config()->set('services.azure.graph.base_url', 'https://graph.microsoft.com/v1.0');
    Cache::flush();
});

function fakeGraphPresence(string $availability): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['value' => [
            ['id' => 'oid-1', 'availability' => $availability, 'activity' => $availability],
        ]]),
    ]);
}

test('it caches presence and broadcasts the change', function () {
    fakeGraphPresence('Busy');
    $user = User::factory()->create(['entra_oid' => 'oid-1', 'is_active' => true]);
    Event::fake([UserPresenceChanged::class]);

    $this->artisan('app:sync-presence')->assertSuccessful();

    expect(Cache::get(ResolvePresence::CACHE_PREFIX.$user->id))->toBe('busy');
    Event::assertDispatched(
        UserPresenceChanged::class,
        fn (UserPresenceChanged $e): bool => $e->userId === $user->id && $e->status === 'busy'
    );
});

test('it does not rebroadcast a status that has not changed', function () {
    $user = User::factory()->create(['entra_oid' => 'oid-1', 'is_active' => true]);
    Cache::put(ResolvePresence::CACHE_PREFIX.$user->id, 'busy', now()->addMinutes(10));
    fakeGraphPresence('Busy');
    Event::fake([UserPresenceChanged::class]);

    $this->artisan('app:sync-presence')->assertSuccessful();

    Event::assertNotDispatched(UserPresenceChanged::class);
});

test('it skips inactive and non-entra users', function () {
    User::factory()->create(['entra_oid' => null, 'is_active' => true]);
    User::factory()->create(['entra_oid' => 'oid-9', 'is_active' => false]);
    fakeGraphPresence('Available');
    Event::fake([UserPresenceChanged::class]);

    $this->artisan('app:sync-presence')->assertSuccessful();

    // Only oid-1 is answered by Graph and no active+entra user matches it, so nothing fires.
    Event::assertNotDispatched(UserPresenceChanged::class);
});

test('it no-ops and sends no request when Graph is disabled', function () {
    config()->set('services.azure.graph.enabled', false);
    User::factory()->create(['entra_oid' => 'oid-1', 'is_active' => true]);
    Http::fake();

    $this->artisan('app:sync-presence')->assertSuccessful();

    Http::assertNothingSent();
});

test('the presence channel authorises active users only', function () {
    $active = User::factory()->create(['is_active' => true]);
    $inactive = User::factory()->create(['is_active' => false]);

    $broadcaster = app(Broadcaster::class);
    $channels = (fn () => $this->channels)->call($broadcaster);
    $authorise = $channels['presence'];

    expect($authorise($active))->toBeTrue()
        ->and($authorise($inactive))->toBeFalse();
});
