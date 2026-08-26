<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Validates a Teams SSO token against Entra's published signing keys, then checks the
 * audience and issuer are ours before trusting any claim. The signing keys are fetched
 * from the tenant's OpenID metadata and cached (they rotate rarely).
 */
final class FirebaseEntraTokenValidator implements EntraTokenValidator
{
    private const string CACHE_KEY = 'entra:jwks:';

    private const int CACHE_TTL_SECONDS = 86400;

    public function validate(string $jwt): array
    {
        $tenant = (string) config('services.azure.tenant');
        $clientId = (string) config('services.azure.client_id');

        if ($tenant === '' || $clientId === '') {
            throw new RuntimeException('Entra credentials are not configured.');
        }

        try {
            $claims = JWT::decode($jwt, JWK::parseKeySet($this->signingKeys($tenant)));
        } catch (Throwable $e) {
            throw new RuntimeException('Token signature or format is invalid: '.$e->getMessage(), previous: $e);
        }

        $this->assertAudience($claims->aud ?? null, $clientId);
        $this->assertIssuer($claims->iss ?? null, $tenant);

        $oid = isset($claims->oid) ? (string) $claims->oid : '';

        if ($oid === '') {
            throw new RuntimeException('Token is missing the oid claim.');
        }

        return [
            'oid' => $oid,
            'email' => $this->email($claims),
        ];
    }

    /**
     * The tenant's current JWT signing keys (JWKS), discovered from its OpenID metadata.
     *
     * @return array<string, mixed>
     */
    private function signingKeys(string $tenant): array
    {
        return Cache::remember(self::CACHE_KEY.$tenant, self::CACHE_TTL_SECONDS, function () use ($tenant): array {
            $metadata = Http::get("https://login.microsoftonline.com/{$tenant}/v2.0/.well-known/openid-configuration")
                ->throw()
                ->json();

            $jwksUri = is_array($metadata) ? ($metadata['jwks_uri'] ?? null) : null;

            if (! is_string($jwksUri)) {
                throw new RuntimeException('Could not resolve the Entra JWKS endpoint.');
            }

            return Http::get($jwksUri)->throw()->json();
        });
    }

    /**
     * The audience must be Mosi: either the raw client ID or the `api://…/{clientId}`
     * Application ID URI exposed for Teams SSO.
     *
     * @param  string|list<string>|null  $aud
     */
    private function assertAudience(array|string|null $aud, string $clientId): void
    {
        $audiences = array_map(strval(...), (array) $aud);

        foreach ($audiences as $audience) {
            if ($audience === $clientId || str_ends_with($audience, "/{$clientId}")) {
                return;
            }
        }

        throw new RuntimeException('Token audience is not Mosi.');
    }

    /**
     * The issuer must be our tenant's Entra authority.
     */
    private function assertIssuer(?string $iss, string $tenant): void
    {
        if ($iss === null || ! str_contains($iss, $tenant)) {
            throw new RuntimeException('Token issuer is not the expected tenant.');
        }
    }

    private function email(object $claims): ?string
    {
        foreach (['preferred_username', 'upn', 'email', 'unique_name'] as $claim) {
            if (isset($claims->{$claim}) && is_string($claims->{$claim}) && $claims->{$claim} !== '') {
                return $claims->{$claim};
            }
        }

        return null;
    }
}
