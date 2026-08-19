<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

/**
 * Resolve the locale the middleware would apply for a given Accept-Language header.
 * Passing null simulates a request that carries no language preference at all — note
 * that Symfony's Request::create() injects a default en-us header, so we strip it.
 */
function localeFor(?string $acceptLanguage): string
{
    $request = Request::create('/probe');
    $request->headers->remove('Accept-Language');
    $request->server->remove('HTTP_ACCEPT_LANGUAGE');

    if ($acceptLanguage !== null) {
        $request->headers->set('Accept-Language', $acceptLanguage);
        $request->server->set('HTTP_ACCEPT_LANGUAGE', $acceptLanguage);
    }

    (new SetLocale)->handle($request, fn () => response('ok'));

    return App::getLocale();
}

describe('Accept-Language resolution', function () {
    it('defaults to Icelandic when no language is requested', function () {
        expect(localeFor(null))->toBe('is');
    });

    it('serves Icelandic to an Icelandic browser', function () {
        expect(localeFor('is-IS,is;q=0.9,en;q=0.5'))->toBe('is');
    });

    it('serves English to an English browser', function () {
        expect(localeFor('en-GB,en;q=0.9'))->toBe('en');
    });

    it('falls back to Icelandic for an unsupported language', function () {
        expect(localeFor('de-DE,de;q=0.9,fr;q=0.8'))->toBe('is');
    });
});

describe('the language switcher', function () {
    beforeEach(function () {
        // A probe route inside the web group so the SetLocale middleware runs. It echoes
        // the resolved locale and a translated string so we can assert both at once.
        Route::middleware('web')->get('/__locale-probe', fn () => App::getLocale().'|'.__('app.tagline'));
    });

    it('lets a saved session preference win over the header', function () {
        $this->withSession(['locale' => 'is'])
            ->withHeader('Accept-Language', 'en-US,en')
            ->get('/__locale-probe')
            ->assertSee('is|Innri vefur starfsfólks NVS', false);
    });

    it('renders English UI strings when English is chosen', function () {
        $this->withSession(['locale' => 'en'])
            ->get('/__locale-probe')
            ->assertSee('en|The NVS staff intranet', false);
    });

    it('stores a supported locale chosen through the switcher', function () {
        $this->get('/locale/en')->assertSessionHas('locale', 'en');
    });

    it('rejects an unsupported locale in the switcher', function () {
        $this->get('/locale/de')
            ->assertNotFound()
            ->assertSessionMissing('locale');
    });
});
