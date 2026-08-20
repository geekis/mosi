<?php

declare(strict_types=1);

use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;

test('the events page lists upcoming visible events', function () {
    $viewer = User::factory()->create();
    Event::factory()->create(['title' => ['is' => 'Sýnilegur', 'en' => 'Visible'], 'starts_at' => now()->addDay()]);

    Livewire::actingAs($viewer)
        ->test('pages::events')
        ->assertOk()
        ->assertSee('Sýnilegur');
});

test('the events page hides secret-group events from non-members', function () {
    $viewer = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    Event::factory()->create(['title' => ['is' => 'Leynifundur', 'en' => 'Secret'], 'group_id' => $secret->id, 'starts_at' => now()->addDay()]);

    Livewire::actingAs($viewer)
        ->test('pages::events')
        ->assertDontSee('Leynifundur');
});

test('a user can create an event from the page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::events')
        ->set('title', 'Vorfundur')
        ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('create')
        ->assertHasNoErrors();

    expect(Event::query()->where('created_by', $user->id)->exists())->toBeTrue();
});

test('a user can rsvp from the events page', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['starts_at' => now()->addDay()]);

    Livewire::actingAs($user)
        ->test('pages::events')
        ->call('respond', $event->id, 'going');

    expect($event->rsvpFor($user))->toBe(RsvpStatus::Going);
});

test('the ics route returns a calendar file for a viewable event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['starts_at' => now()->addDay()]);

    $this->actingAs($user)
        ->get(route('events.ics', $event))
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8');
});

test('the ics route is forbidden for a secret-group event the user cannot see', function () {
    $user = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $event = Event::factory()->create(['group_id' => $secret->id, 'starts_at' => now()->addDay()]);

    $this->actingAs($user)
        ->get(route('events.ics', $event))
        ->assertForbidden();
});
