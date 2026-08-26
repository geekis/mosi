<?php

declare(strict_types=1);

use App\Actions\Auth\FirebaseEntraTokenValidator;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.azure.tenant', 'tenant-abc');
    config()->set('services.azure.client_id', 'client-123');
    config()->set('cache.default', 'array');
});

/**
 * Mint an RS256 token signed by a throwaway key and fake Entra's discovery + JWKS so the
 * validator verifies it against the matching public key. Returns the encoded JWT.
 *
 * @param  array<string, mixed>  $claims
 */
function fakeEntraToken(array $claims = []): string
{
    // A committed throwaway key — runtime key generation needs openssl.cnf, which is
    // absent on some dev machines, whereas parsing and signing an existing key do not.
    $privateKey = (string) file_get_contents(base_path('tests/Fixtures/entra-test-key.pem'));

    $details = openssl_pkey_get_details(openssl_pkey_get_private($privateKey));
    $base64Url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    // No per-key "alg" — mirrors Microsoft's real JWKS, which forces the validator to
    // supply the default algorithm to parseKeySet().
    $kid = 'test-key';
    $jwks = ['keys' => [[
        'kty' => 'RSA',
        'use' => 'sig',
        'kid' => $kid,
        'n' => $base64Url($details['rsa']['n']),
        'e' => $base64Url($details['rsa']['e']),
    ]]];

    Http::fake([
        'https://login.microsoftonline.com/tenant-abc/v2.0/.well-known/openid-configuration' => Http::response([
            'jwks_uri' => 'https://login.microsoftonline.com/tenant-abc/discovery/v2.0/keys',
        ]),
        'https://login.microsoftonline.com/tenant-abc/discovery/v2.0/keys' => Http::response($jwks),
    ]);

    $now = time();
    $payload = array_merge([
        'aud' => 'client-123',
        'iss' => 'https://login.microsoftonline.com/tenant-abc/v2.0',
        'iat' => $now,
        'nbf' => $now,
        'exp' => $now + 3600,
        'oid' => 'oid-xyz',
        'preferred_username' => 'egill@eigind.is',
    ], $claims);

    return JWT::encode($payload, $privateKey, 'RS256', $kid);
}

test('a properly signed token returns the identity claims', function () {
    $identity = app(FirebaseEntraTokenValidator::class)->validate(fakeEntraToken());

    expect($identity)->toBe(['oid' => 'oid-xyz', 'email' => 'egill@eigind.is']);
});

test('the Application ID URI is an accepted audience', function () {
    $token = fakeEntraToken(['aud' => 'api://mosi.test/client-123']);

    expect(app(FirebaseEntraTokenValidator::class)->validate($token)['oid'])->toBe('oid-xyz');
});

test('a token for another audience is rejected', function () {
    $token = fakeEntraToken(['aud' => 'some-other-app']);

    app(FirebaseEntraTokenValidator::class)->validate($token);
})->throws(RuntimeException::class);

test('a token from another tenant is rejected', function () {
    $token = fakeEntraToken(['iss' => 'https://login.microsoftonline.com/evil-tenant/v2.0']);

    app(FirebaseEntraTokenValidator::class)->validate($token);
})->throws(RuntimeException::class);

test('an expired token is rejected', function () {
    $token = fakeEntraToken(['iat' => time() - 7200, 'nbf' => time() - 7200, 'exp' => time() - 3600]);

    app(FirebaseEntraTokenValidator::class)->validate($token);
})->throws(RuntimeException::class);

test('a token without an oid is rejected', function () {
    $token = fakeEntraToken(['oid' => null]);

    app(FirebaseEntraTokenValidator::class)->validate($token);
})->throws(RuntimeException::class);
