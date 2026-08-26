<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EntraTokenValidator;
use App\Actions\Auth\ResolveEntraLogin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Silent single sign-on inside the Microsoft Teams tab. The tab loads {@see self::show()},
 * which — for a guest — runs the Teams JS SSO handshake and posts the resulting token to
 * {@see self::store()} to establish a Mosi session. Reuses the same account resolution as
 * the browser Entra flow, so matching and linking behave identically (.ai/rules/auth.md).
 */
class TeamsAuthController extends Controller
{
    /**
     * Land the Teams tab: send an already-authenticated user straight in, otherwise
     * render the bootstrap page that performs the silent sign-in.
     */
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('pages::auth.teams');
    }

    /**
     * Exchange a validated Teams SSO token for a Mosi session. Fails closed with a
     * generic response; the real reason is logged for setup and ops.
     */
    public function store(Request $request, EntraTokenValidator $validator, ResolveEntraLogin $resolve): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->string('token')->toString();

        if (blank($token)) {
            return $this->reject(422);
        }

        try {
            $identity = $validator->validate($token);
        } catch (Throwable $e) {
            Log::warning('Teams SSO token rejected.', ['reason' => $e->getMessage()]);

            return $this->reject(401);
        }

        $user = $resolve($identity['oid'], $identity['email']);

        if ($user === null) {
            Log::warning('Teams SSO rejected: no active staff record.', [
                'oid' => $identity['oid'],
                'email' => $identity['email'],
            ]);

            return $this->reject(403);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }

    private function reject(int $status): JsonResponse
    {
        return response()->json(['message' => __('auth.failed')], $status);
    }
}
