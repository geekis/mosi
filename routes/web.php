<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Language switcher: persist an explicit locale choice, then return to the previous page.
// SetLocale reads this session value on the next request.
Route::get('locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, config('app.supported_locales'), true), 404);

    session()->put('locale', $locale);

    return back();
})->name('locale.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
