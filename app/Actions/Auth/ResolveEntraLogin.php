<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\AuthMethod;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Resolve the Mosi account for an Entra (Azure AD) sign-in. SSO never creates users —
 * accounts come only from the staff sync — so an unmatched or inactive identity is
 * rejected (.ai/rules/auth.md).
 *
 * Matching is by the immutable `oid` first, falling back to email on the very first
 * login so the account can be bound. A `password` account that gains a Microsoft
 * identity is upgraded to `both` in place; a duplicate is never created.
 */
final class ResolveEntraLogin
{
    /**
     * @return User|null The account to log in, or null when the sign-in must be rejected
     *                   (unknown identity or deactivated account — treated identically so
     *                   the flow cannot be used to enumerate staff).
     */
    public function __invoke(string $oid, ?string $email): ?User
    {
        $user = $this->match($oid, $email);

        if ($user === null || ! $user->is_active) {
            return null;
        }

        // First Entra login for this record: bind the immutable oid, and promote a
        // password-only account to `both` now that it also has a Microsoft identity.
        if ($user->entra_oid === null) {
            $user->entra_oid = $oid;

            if ($user->auth_method === AuthMethod::Password) {
                $user->auth_method = AuthMethod::Both;
            }

            $user->save();
        }

        return $user;
    }

    private function match(string $oid, ?string $email): ?User
    {
        $user = User::query()->where('entra_oid', $oid)->first();

        if ($user !== null || $email === null || $email === '') {
            return $user;
        }

        // Case-insensitive email fallback, portable across MySQL and the sqlite test DB.
        return User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();
    }
}
