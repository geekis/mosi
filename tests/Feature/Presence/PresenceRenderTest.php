<?php

declare(strict_types=1);

use App\Actions\Presence\ResolvePresence;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('services.azure.graph.enabled', true);
    Cache::flush();
});

test('the avatar wrapper renders a presence dot seeded with the resolved status', function () {
    $user = User::factory()->create(['entra_oid' => null, 'last_active_at' => now()]);

    $html = Blade::render('<x-avatar :user="$user" size="sm" />', ['user' => $user]);

    expect($html)->toContain('presenceDot(')
        ->toContain("'available'");
});

test('a Graph-synced status seeds the dot over the heartbeat', function () {
    $user = User::factory()->create(['entra_oid' => 'oid-1', 'last_active_at' => now()->subHour()]);
    Cache::put(ResolvePresence::CACHE_PREFIX.$user->id, 'busy', 600);

    $html = Blade::render('<x-presence-dot :user="$user" />', ['user' => $user]);

    expect($html)->toContain("'busy'")
        ->toContain('bg-red-500');
});

test('the presence line shows a last-active fallback for an offline heartbeat user', function () {
    $user = User::factory()->create(['entra_oid' => null, 'last_active_at' => now()->subHours(2)]);

    $html = Blade::render('<x-user-presence :user="$user" />', ['user' => $user]);

    expect($html)->toContain('presenceLine(')
        ->toContain(__('Last active :time', ['time' => $user->last_active_at->diffForHumans()]));
});

test('an avatar with presence disabled renders no dot', function () {
    $user = User::factory()->create();

    $html = Blade::render('<x-avatar :user="$user" :presence="false" />', ['user' => $user]);

    expect($html)->not->toContain('presenceDot(');
});
