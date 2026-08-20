<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\EventIcsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Staff invitation: set-password flow via the dedicated `invitations` password broker.
Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.accept');
    Route::post('invitation', [InvitationController::class, 'store'])->name('invitation.store');
});

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
