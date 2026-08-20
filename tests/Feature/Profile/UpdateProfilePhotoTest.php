<?php

declare(strict_types=1);

use App\Actions\Profile\UpdateProfilePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

test('it stores an uploaded avatar in the avatar collection', function () {
    $user = User::factory()->create();

    app(UpdateProfilePhoto::class)($user, UploadedFile::fake()->image('me.jpg', 200, 200), 'avatar');

    expect($user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
});

test('it stores an uploaded banner in the banner collection', function () {
    $user = User::factory()->create();

    app(UpdateProfilePhoto::class)($user, UploadedFile::fake()->image('cover.jpg', 1200, 400), 'banner');

    expect($user->fresh()->getFirstMedia('banner'))->not->toBeNull();
});

test('a new avatar replaces the previous one', function () {
    $user = User::factory()->create();

    app(UpdateProfilePhoto::class)($user, UploadedFile::fake()->image('a.jpg'), 'avatar');
    app(UpdateProfilePhoto::class)($user, UploadedFile::fake()->image('b.jpg'), 'avatar');

    expect($user->fresh()->getMedia('avatar'))->toHaveCount(1);
});

test('it rejects an unknown collection', function () {
    app(UpdateProfilePhoto::class)(User::factory()->create(), UploadedFile::fake()->image('x.jpg'), 'nope');
})->throws(InvalidArgumentException::class);
