<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a user can upload an avatar from settings', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('avatar', UploadedFile::fake()->image('me.png', 300, 300))
        ->call('saveAvatar')
        ->assertHasNoErrors();

    expect($user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
});

test('a non-image upload is rejected', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('avatar', UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'))
        ->call('saveAvatar')
        ->assertHasErrors(['avatar']);

    expect($user->fresh()->getFirstMedia('avatar'))->toBeNull();
});

test('the bio is saved with the profile form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('bio', 'Ég vinn í náttúruvernd.')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->bio)->toBe('Ég vinn í náttúruvernd.');
});
