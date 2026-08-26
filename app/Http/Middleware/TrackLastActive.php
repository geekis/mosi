<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamp the authenticated user's last_active_at for presence. Throttled to at most once
 * per minute and written with a bare query so it never bumps updated_at or fires model
 * events on every request.
 */
class TrackLastActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ($user->last_active_at === null || $user->last_active_at->lt(now()->subMinute()))) {
            $now = now();
            DB::table('users')->where('id', $user->id)->update(['last_active_at' => $now]);
            // Reflect it on the loaded model for the rest of this request, without a save.
            $user->forceFill(['last_active_at' => $now]);
        }

        return $next($request);
    }
}
