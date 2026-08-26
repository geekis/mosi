<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force super-admins and comms staff to enrol in two-factor authentication before they
 * can use the rest of the app (.ai/rules/auth.md). They are redirected to the security
 * settings until 2FA is confirmed; the routes needed to actually enrol are let through.
 */
class RequireTwoFactorForPrivilegedRoles
{
    /** @var list<string> */
    private const array PRIVILEGED_ROLES = ['super-admin', 'comms'];

    public function handle(Request $request, Closure $next): Response
    {
        // Local/dev convenience: skip the mandatory-2FA gate so super-admins and comms
        // staff can use the app without enrolling. Production and the testing environment
        // keep 2FA mandatory (.ai/rules/auth.md), so the feature tests still exercise it.
        if (app()->environment('local', 'dev')) {
            return $next($request);
        }

        // Let the routes needed to enrol through first — the security settings page,
        // Fortify's 2FA/password-confirm endpoints (under /user), Livewire updates and
        // logout. This also avoids the role query on every Livewire poll.
        if ($request->routeIs('security.edit', 'appearance.edit', 'logout')
            || $request->is('user/*', 'livewire/*')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User
            && $user->two_factor_confirmed_at === null
            && $this->isPrivileged($user)) {
            return redirect()->route('security.edit')
                ->with('status', __('auth.two_factor_required'));
        }

        return $next($request);
    }

    private function isPrivileged(User $user): bool
    {
        // Query the relation (not the lazy-loaded attribute) so the strict lazy-loading
        // guard is not tripped on every request.
        return $user->roles()->whereIn('name', self::PRIVILEGED_ROLES)->exists();
    }
}
