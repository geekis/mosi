<?php

declare(strict_types=1);

use App\Actions\Microsoft\AcquireGraphToken;
use App\Support\Microsoft\GraphClient;
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

test('it acquires an app-only token once and reuses it from cache', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600]),
    ]);

    $token = app(AcquireGraphToken::class);

    expect($token())->toBe('abc123')
        ->and($token())->toBe('abc123');

    Http::assertSentCount(1);
});

test('it throws when the Graph credentials are not configured', function () {
    config()->set('services.azure.client_secret', '');

    expect(fn () => app(AcquireGraphToken::class)())
        ->toThrow(RuntimeException::class);
});

test('the client injects the bearer token and returns decoded json', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600]),
        'graph.microsoft.com/v1.0/me' => Http::response(['id' => 'u1']),
    ]);

    $result = app(GraphClient::class)->get('me');

    expect($result)->toBe(['id' => 'u1']);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/me'
        && $request->hasHeader('Authorization', 'Bearer abc123'));
});

test('it retries once on a 429 and returns the follow-up response', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::sequence()
            ->push(['error' => 'throttled'], 429, ['Retry-After' => '0'])
            ->push(['id' => 'u1'], 200),
    ]);

    $result = app(GraphClient::class)->get('me');

    expect($result)->toBe(['id' => 'u1']);
});

test('it makes no HTTP call and returns null when Graph is disabled', function () {
    config()->set('services.azure.graph.enabled', false);
    Http::fake();

    expect(app(GraphClient::class)->get('me'))->toBeNull();

    Http::assertNothingSent();
});
