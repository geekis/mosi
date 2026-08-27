<?php

declare(strict_types=1);

namespace App\Support\Microsoft;

use App\Actions\Microsoft\AcquireGraphToken;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Microsoft Graph REST API: injects the app-only bearer token,
 * prepends the configured base URL, and honours a 429 `Retry-After` once before giving
 * up. Every call is gated on `services.azure.graph.enabled` so that until the tenant
 * grants Graph consent the whole feature is dark — callers get `null` and fall back to
 * their non-Graph behaviour rather than erroring.
 */
final class GraphClient
{
    /**
     * Cap on how long we will wait for a throttled request, so a hostile `Retry-After`
     * can never wedge a queue worker or a web request.
     */
    private const int MAX_RETRY_AFTER_SECONDS = 30;

    public function __construct(private readonly AcquireGraphToken $token) {}

    public function enabled(): bool
    {
        return (bool) config('services.azure.graph.enabled');
    }

    /**
     * GET a Graph resource. Returns the decoded body, or null when Graph is disabled.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->get($this->url($path), $query));
    }

    /**
     * POST a JSON body to a Graph resource. Returns the decoded body, or null when
     * Graph is disabled.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    public function post(string $path, array $body = []): ?array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->post($this->url($path), $body));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>|null
     */
    private function send(callable $call): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $response = $call($this->request());

        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('Retry-After');

            if ($retryAfter > 0 && $retryAfter <= self::MAX_RETRY_AFTER_SECONDS) {
                sleep($retryAfter);
            }

            $response = $call($this->request());
        }

        return $response->throw()->json();
    }

    private function request(): PendingRequest
    {
        return Http::withToken(($this->token)())->acceptJson();
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('services.azure.graph.base_url'), '/');

        return $base.'/'.ltrim($path, '/');
    }
}
