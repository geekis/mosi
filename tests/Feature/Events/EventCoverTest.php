<?php

declare(strict_types=1);

use App\Actions\Events\UpdateEventCover;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

test('an event has no cover until one is uploaded', function () {
    $event = Event::factory()->create();

    expect($event->coverUrl())->toBeNull();

    app(UpdateEventCover::class)($event, UploadedFile::fake()->image('cover.jpg', 1200, 600));

    expect($event->fresh()->coverUrl())->not->toBeNull()
        ->and($event->fresh()->getFirstMedia('cover'))->not->toBeNull();
});

test('a new cover replaces the previous one', function () {
    $event = Event::factory()->create();

    app(UpdateEventCover::class)($event, UploadedFile::fake()->image('a.jpg'));
    app(UpdateEventCover::class)($event, UploadedFile::fake()->image('b.jpg'));

    expect($event->fresh()->getMedia('cover'))->toHaveCount(1);
});

test('creating an event with a cover attaches it', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::events')
        ->set('title', 'Sumarhátíð')
        ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
        ->set('cover', UploadedFile::fake()->image('party.jpg', 1000, 500))
        ->call('create');

    $event = Event::query()->where('created_by', $user->id)->firstOrFail();

    expect($event->getFirstMedia('cover'))->not->toBeNull();
});

test('an event can be created without a cover', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::events')
        ->set('title', 'Fundur')
        ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('create');

    $event = Event::query()->where('created_by', $user->id)->firstOrFail();

    expect($event->coverUrl())->toBeNull();
});

test('the creator can change the cover from the event page', function () {
    $creator = User::factory()->create();
    $event = Event::factory()->create(['created_by' => $creator->id]);

    Livewire::actingAs($creator)
        ->test('pages::events.show', ['event' => $event])
        ->set('coverFile', UploadedFile::fake()->image('new.jpg'))
        ->call('saveCover');

    expect($event->fresh()->getFirstMedia('cover'))->not->toBeNull();
});

test('a non-organiser cannot change the cover', function () {
    $event = Event::factory()->create();
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::events.show', ['event' => $event])
        ->set('coverFile', UploadedFile::fake()->image('new.jpg'))
        ->call('saveCover')
        ->assertForbidden();

    expect($event->fresh()->getFirstMedia('cover'))->toBeNull();
});
