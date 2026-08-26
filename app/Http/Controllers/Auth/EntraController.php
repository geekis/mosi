<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveEntraLogin;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Microsoft Entra (Azure AD) single sign-on. The redirect URI registered in Entra must
 * match {@see self::callback()} exactly: `/auth/entra/callback` (.ai/rules/auth.md).
 */
class EntraController extends Controller
{
    /**
     * Send the user to Microsoft to authenticate. When the identifier-first step passed
     * an email along, forward it as a `login_hint` so Microsoft pre-fills the account.
     */
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        $driver = Socialite::driver('azure');

        // `with()` lives on the concrete OAuth2 provider, not the Socialite contract.
        if ($driver instanceof AbstractProvider && filled($hint = $request->string('login_hint')->toString())) {
            $driver->with(['login_hint' => $hint]);
        }

        return $driver->redirect();
    }

    /**
     * Handle the callback from Microsoft: resolve the staff account, or fail closed with
     * one generic message that never reveals whether an account exists or is active.
     */
    public function callback(Request $request, ResolveEntraLogin $resolve): RedirectResponse
    {
        try {
            $entraUser = Socialite::driver('azure')->user();
        } catch (Throwable $e) {
            // The user only ever sees the generic reject; the real cause (missing code,
            // bad secret, redirect-URI mismatch) is logged for setup and ops debugging.
            // The message describes the OAuth exchange failure, not any token or secret.
            Log::warning('Entra SSO callback failed.', ['reason' => $e->getMessage()]);

            return $this->reject();
        }

        $user = $resolve($entraUser->getId(), $entraUser->getEmail());

        if ($user === null) {
            // Authentication succeeded but no active staff record matched. Logged so a
            // rejected sign-in can be told apart from an OAuth failure during setup.
            Log::warning('Entra SSO rejected: no active staff record.', [
                'oid' => $entraUser->getId(),
                'email' => $entraUser->getEmail(),
            ]);

            return $this->reject();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function reject(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => __('auth.failed'),
        ]);
    }
}
