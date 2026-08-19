<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the request locale and apply it to the framework and Carbon.
 *
 * Resolution order (highest priority first):
 *   1. An explicit choice the user made with the language switcher (session).
 *   2. The authenticated user's saved preference (the `locale` column arrives in
 *      phase 1; until then this is simply skipped).
 *   3. The browser's `Accept-Language` header, matched against supported locales.
 *   4. Icelandic — the default.
 *
 * Phase 1 inserts the staff-sync `nationality` hint between steps 2 and 3
 * (see .ai/rules/localisation.md).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', ['is', 'en']);

        if ($request->hasSession()) {
            $stored = $request->session()->get('locale');

            if (is_string($stored) && in_array($stored, $supported, true)) {
                return $stored;
            }
        }

        // The `locale` column is added in phase 1; until then this is simply null.
        $preference = $request->user()?->getAttribute('locale');

        if (is_string($preference) && in_array($preference, $supported, true)) {
            return $preference;
        }

        // Walk the browser's ordered Accept-Language preferences and take the first
        // whose primary subtag we support. A request that expresses no supported
        // preference (or none at all) falls through to the first supported locale,
        // which is Icelandic. We match by primary subtag ourselves rather than using
        // getPreferredLanguage(), whose absent-header fallback is not "first supported".
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(substr($language, 0, 2));

            if (in_array($primary, $supported, true)) {
                return $primary;
            }
        }

        return $supported[0];
    }
}
