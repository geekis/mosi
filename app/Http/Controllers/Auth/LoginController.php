<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Identifier-first login. One email field decides the path: a Microsoft-domain address
 * is sent to Entra SSO, anything else reveals the password field. The decision is by
 * email domain alone — never a database lookup — so the form cannot be used to discover
 * who works at NVS (.ai/rules/auth.md).
 */
class LoginController extends Controller
{
    /**
     * Step one: take the email and either redirect to Microsoft or bounce back to the
     * login page with the password field revealed.
     */
    public function identify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = $validated['email'];

        if (self::isEntraDomain($email)) {
            return redirect()->route('entra.redirect', ['login_hint' => $email]);
        }

        // Not a Microsoft account: re-render login with the email remembered and the
        // password field shown. An unknown email behaves identically, so nothing leaks.
        return redirect()->route('login')->withInput(['email' => $email]);
    }

    /**
     * Whether an address belongs to the Microsoft-federated domain and should therefore
     * be sent to Entra rather than shown a password field.
     */
    public static function isEntraDomain(?string $email): bool
    {
        $domain = (string) config('services.azure.entra_domain');

        if ($email === null || $domain === '') {
            return false;
        }

        return Str::endsWith(Str::lower(trim($email)), '@'.Str::lower($domain));
    }
}
