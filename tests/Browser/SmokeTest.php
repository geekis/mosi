<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Browser smoke test
|--------------------------------------------------------------------------
|
| Pest 4 browser testing drives a real Chromium via Playwright. These tests are
| NOT part of the default `php artisan test` run (they need Playwright installed);
| run them explicitly with `php artisan test tests/Browser` after:
|
|   npm install
|   npx playwright install chromium
|
| Phase 1D adds end-to-end browser coverage for both login paths, and later phases
| cover the feed and the post composer (see .ai/rules/testing.md).
|
*/

it('renders the home page without JavaScript errors', function () {
    visit('/')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
