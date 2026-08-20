<?php

declare(strict_types=1);

use App\Actions\Events\BuildEventIcs;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\RespondToEvent;
use App\Enums\GroupRole;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;

test('creating an organisation-wide event stores it', function () {
    $creator = User::factory()->create();

    $event = app(CreateEvent::class)($creator, [
        'title' => ['is' => 'Fundur', 'en' => 'Meeting'],
        'starts_at' => now()->addWeek(),
    ]);

    expect($event->group_id)->toBeNull()
        ->and($event->created_by)->toBe($creator->id)
        ->and($event->slug)->not->toBeEmpty();
});

test('creating a group event requires membership', function () {
    $creator = User::factory()->create();
    $group = Group::factory()->create();

    app(CreateEvent::class)($creator, [
        'title' => ['is' => 'Hópfundur', 'en' => 'Group meeting'],
        'starts_at' => now()->addWeek(),
        'group_id' => $group->id,
    ]);
})->throws(DomainException::class);

test('an rsvp records and then changes status', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    app(RespondToEvent::class)($user, $event, RsvpStatus::Going);
    expect($event->rsvpFor($user))->toBe(RsvpStatus::Going);

    app(RespondToEvent::class)($user, $event, RsvpStatus::Declined);
    expect($event->fresh()->rsvpFor($user))->toBe(RsvpStatus::Declined);

    app(RespondToEvent::class)($user, $event, null);
    expect($event->fresh()->rsvpFor($user))->toBeNull();
});

test('the upcoming scope excludes past events', function () {
    $future = Event::factory()->create(['starts_at' => now()->addDay()]);
    $past = Event::factory()->past()->create();

    $ids = Event::query()->upcoming()->pluck('id')->all();

    expect($ids)->toContain($future->id)->not->toContain($past->id);
});

test('an event in a secret group is invisible to a non-member', function () {
    $viewer = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $event = Event::factory()->create(['group_id' => $secret->id]);

    $ids = Event::query()->visibleTo($viewer)->pluck('id')->all();

    expect($ids)->not->toContain($event->id)
        ->and($viewer->can('view', $event))->toBeFalse();
});

test('an event in a secret group is visible to a member', function () {
    $member = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($member->id, ['role' => GroupRole::Member->value]);
    $event = Event::factory()->create(['group_id' => $secret->id]);

    expect($member->can('view', $event))->toBeTrue();
});

test('the ics export produces a valid calendar document', function () {
    $event = Event::factory()->create([
        'title' => ['is' => 'Ráðstefna', 'en' => 'Conference'],
        'location' => 'Harpa',
        'starts_at' => now()->addWeek(),
    ]);

    $ics = app(BuildEventIcs::class)($event);

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('SUMMARY:Ráðstefna')
        ->toContain('LOCATION:Harpa')
        ->toContain('END:VCALENDAR');
});
