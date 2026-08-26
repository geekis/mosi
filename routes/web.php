<?php

use App\Http\Controllers\Auth\EntraController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TeamsAuthController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\EventIcsController;
use App\Http\Controllers\GroupMediaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Staff invitation: set-password flow via the dedicated `invitations` password broker.
Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.accept');
    Route::post('invitation', [InvitationController::class, 'store'])->name('invitation.store');
});

// Identifier-first login: the email step that routes to Microsoft or the password field.
Route::middleware(['guest', 'throttle:login'])
    ->post('login/identify', [LoginController::class, 'identify'])
    ->name('login.identify');

// Microsoft Entra SSO. The callback path must match the redirect URI registered in
// Entra exactly (.ai/rules/auth.md).
Route::middleware('guest')->group(function () {
    Route::get('auth/entra/redirect', [EntraController::class, 'redirect'])->name('entra.redirect');
    Route::get('auth/entra/callback', [EntraController::class, 'callback'])->name('entra.callback');
});

// Teams tab silent SSO. `show` is the tab's landing page; `store` exchanges the Teams
// JS token for a session. Not guest-only: an already-signed-in user is sent straight in.
Route::get('teams/auth', [TeamsAuthController::class, 'show'])->name('teams.auth');
Route::post('teams/auth', [TeamsAuthController::class, 'store'])->name('teams.auth.store');

// Language switcher: persist an explicit locale choice, then return to the previous page.
// SetLocale reads this session value on the next request.
Route::get('locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, config('app.supported_locales'), true), 404);

    session()->put('locale', $locale);

    return back();
})->name('locale.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::feed')->name('dashboard');
    Route::livewire('directory', 'pages::directory')->name('directory');
    Route::livewire('people/{user:external_id}', 'pages::people.show')->name('people.show');

    Route::livewire('groups', 'pages::groups')->name('groups');
    Route::livewire('groups/{group:slug}', 'pages::groups.show')->name('groups.show');
    // Members-only file/gallery streaming (private disk, gated by group membership).
    Route::get('groups/{group:slug}/attachments/{media}', GroupMediaController::class)->name('groups.attachments');

    Route::livewire('events', 'pages::events')->name('events');
    Route::livewire('events/{event:slug}', 'pages::events.show')->name('events.show');
    Route::get('events/{event:slug}/ics', EventIcsController::class)->name('events.ics');

    Route::livewire('notifications', 'pages::notifications')->name('notifications');

    Route::livewire('chat', 'pages::chat')->name('chat');
    Route::livewire('search', 'pages::search')->name('search');

    Route::livewire('news', 'pages::news')->name('news');
    Route::livewire('documents', 'pages::documents')->name('documents');
    Route::get('documents/{document}/download', DocumentDownloadController::class)->name('documents.download');
    Route::livewire('p/{page:slug}', 'pages::page')->name('pages.show');

    Route::get('me/export', DataExportController::class)->name('me.export');
});

require __DIR__.'/settings.php';
