<?php

declare(strict_types=1);

namespace App\Actions\Microsoft;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Acquires an app-only Microsoft Graph access token via the client-credentials flow and
 * caches it until shortly before it expires. Reuses the existing Entra app registration
 * (the same client id/secret used for SSO); the app-level Graph permissions
 * (`Presence.Read.All`, `Calendars.Read`) must be admin-consented for the returned token
 * to carry them. The flow issues no refresh token, so on expiry we simply mint another.
 */
final class AcquireGraphToken
{
    private const string CACHE_KEY = 'graph:app-token:';

    /**
     * Shave a minute off the reported lifetime so a token never expires mid-request.
     */
    private const int EXPIRY_MARGIN_SECONDS = 60;

    public function __invoke(): string
    {
        $tenant = (string) config('services.azure.tenant');
        $clientId = (string) config('services.azure.client_id');
        $clientSecret = (string) config('services.azure.client_secret');

        if ($tenant === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Microsoft Graph credentials are not configured.');
        }

        $cached = Cache::get(self::CACHE_KEY.$tenant);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ])
            ->throw()
            ->json();

        $token = is_array($response) ? ($response['access_token'] ?? null) : null;
        $expiresIn = is_array($response) ? (int) ($response['expires_in'] ?? 0) : 0;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Microsoft Graph did not return an access token.');
        }

        Cache::put(self::CACHE_KEY.$tenant, $token, max(1, $expiresIn - self::EXPIRY_MARGIN_SECONDS));

        return $token;
    }
}
