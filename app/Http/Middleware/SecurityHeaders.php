<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline hardening headers on every web response. A full Content-Security-Policy needs
 * per-request nonces to coexist with Livewire/Flux inline scripts and is configured at
 * the edge (Laravel Cloud); these are the safe, app-level defaults.
 *
 * Exception: when the app is opened as a Microsoft Teams tab (V2 phase 15,
 * .ai/rules/frontend.md) the page must be framable by the Teams clients, so the
 * default `X-Frame-Options: SAMEORIGIN` is replaced with a CSP `frame-ancestors` that
 * whitelists the Teams host set. Everywhere else framing stays same-origin only.
 */
class SecurityHeaders
{
    /**
     * Cookie set once the Teams tab is entered, so full-document reloads inside the
     * frame keep the relaxed framing headers even after the `?context=teams` query
     * param is dropped by later navigation.
     */
    private const string TEAMS_EMBED_COOKIE = 'mosi_teams_embed';

    /**
     * Hosts allowed to frame Mosi when running as a Teams tab.
     */
    private const string TEAMS_FRAME_ANCESTORS = "frame-ancestors 'self' https://teams.microsoft.com https://*.teams.microsoft.com https://*.skype.com https://*.office.com https://*.microsoft.com;";

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $inTeams = self::isTeamsEmbed($request);

        if ($inTeams) {
            // The tab frames Mosi cross-site, so the session and CSRF cookies must be
            // SameSite=None; Secure to be sent inside the iframe at all. Set before
            // StartSession writes them on the way out; left Lax for every other request.
            config([
                'session.same_site' => 'none',
                'session.secure' => true,
            ]);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($inTeams) {
            $response->headers->set('Content-Security-Policy', self::TEAMS_FRAME_ANCESTORS);
            $this->rememberTeamsEmbed($response);
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }

    /**
     * A request is a Teams embed when it arrives with the manifest's `?context=teams`
     * marker, or when the persistence cookie from an earlier framed load is present.
     * Drives the framing headers and cookie SameSite only — the account menu is hidden
     * client-side by actual frame detection (partials/head), which cannot leak into a
     * normal browser session the way this cookie can.
     */
    public static function isTeamsEmbed(Request $request): bool
    {
        return $request->query('context') === 'teams'
            || $request->cookie(self::TEAMS_EMBED_COOKIE) === '1';
    }

    /**
     * Persist the Teams-embed marker. The cookie must be SameSite=None; Secure so the
     * browser sends it back inside the cross-site Teams iframe.
     */
    private function rememberTeamsEmbed(Response $response): void
    {
        $response->headers->setCookie(
            cookie()->make(
                name: self::TEAMS_EMBED_COOKIE,
                value: '1',
                minutes: 0,
                secure: true,
                httpOnly: true,
                sameSite: 'none',
            ),
        );
    }
}
