<?php

use App\Http\Middleware\RequireTwoFactorForPrivilegedRoles;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLastActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
            RequireTwoFactorForPrivilegedRoles::class,
            TrackLastActive::class,
        ]);

        // Non-sensitive marker read to relax framing for the Teams tab; kept
        // unencrypted so it survives the cross-site Teams iframe round-trip and the
        // raw value stays readable (see SecurityHeaders).
        $middleware->encryptCookies(except: [
            'mosi_teams_embed',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
