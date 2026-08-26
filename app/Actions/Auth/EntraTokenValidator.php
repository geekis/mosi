<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use RuntimeException;

/**
 * Validates a Microsoft Entra access token minted for Mosi by Teams SSO
 * (`authentication.getAuthToken()`), returning the identity claims it can be trusted
 * for. Bound to {@see FirebaseEntraTokenValidator}; swapped for a fake in tests.
 */
interface EntraTokenValidator
{
    /**
     * @return array{oid: string, email: string|null}
     *
     * @throws RuntimeException when the token is missing a key claim, has a wrong
     *                          audience or issuer, is expired, or fails signature checks.
     */
    public function validate(string $jwt): array;
}
