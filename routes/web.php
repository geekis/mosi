<?php

use App\Http\Controllers\Auth\InvitationController;
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
    Route::livewire('people/{user:external_id}', 'pages::people.show')->name('people.show');
});

require __DIR__.'/settings.php';
