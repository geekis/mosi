<?php

declare(strict_types=1);

use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Models\User;
use Livewire\Livewire;

test('isOnline reflects recent activity', function () {
    $online = (new User)->forceFill(['last_active_at' => now()->subMinutes(2)]);
    $away = (new User)->forceFill(['last_active_at' => now()->subMinutes(30)]);
    $never = (new User)->forceFill(['last_active_at' => null]);

    expect($online->isOnline())->toBeTrue()
        ->and($away->isOnline())->toBeFalse()
        ->and($never->isOnline())->toBeFalse();
});

test('an authenticated request stamps last_active_at when it was empty', function () {
    $user = User::factory()->create();
    expect($user->last_active_at)->toBeNull();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->last_active_at)->not->toBeNull();
});

test('last_active_at is not rewritten within the throttle window', function () {
    $recent = now()->subSeconds(10);
    $user = User::factory()->create();
    $user->forceFill(['last_active_at' => $recent])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->last_active_at->timestamp)->toBe($recent->timestamp);
});

test('last_active_at refreshes after a minute of inactivity', function () {
    $stale = now()->subMinutes(5);
    $user = User::factory()->create();
    $user->forceFill(['last_active_at' => $stale])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->last_active_at->greaterThan($stale))->toBeTrue();
});

test('the directory renders with an active user and their presence dot', function () {
    $viewer = User::factory()->create();
    $active = User::factory()->create(['display_name' => 'Virk Vaktkona']);
    $active->forceFill(['last_active_at' => now()])->save();

    Livewire::actingAs($viewer)
        ->test('pages::directory')
        ->assertOk()
        ->assertSee('Virk Vaktkona');
});

test('the chat thread shows the other participant as active', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $other->forceFill(['last_active_at' => now()])->save();

    $dm = app(StartDirectConversation::class)($me, $other);
    app(SendMessage::class)($other, $dm, 'hæ');

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $dm->id])
        ->assertSee(__('Active now'));
});
