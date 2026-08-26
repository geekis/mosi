<?php

declare(strict_types=1);

use App\Actions\Chat\EnsureGroupConversation;
use App\Actions\Groups\AcceptGroupInvitation;
use App\Actions\Groups\DeclineGroupInvitation;
use App\Actions\Groups\InviteToGroup;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\User;
use App\Notifications\GroupInvitationReceived;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function invitationOwnerOf(Group $group): User
{
    $owner = User::factory()->create();
    $group->members()->attach($owner->id, ['role' => GroupRole::Owner->value]);

    return $owner;
}

test('an owner invites a colleague and the invitee is notified once', function () {
    Notification::fake();
    $group = Group::factory()->secret()->create();
    $owner = invitationOwnerOf($group);
    $invitee = User::factory()->create();

    $invitation = app(InviteToGroup::class)($owner, $group, $invitee);

    expect($invitation->invited_by)->toBe($owner->id);
    Notification::assertSentTo($invitee, GroupInvitationReceived::class);

    // A repeat invite reuses the pending invitation and does not notify again.
    $again = app(InviteToGroup::class)($owner, $group, $invitee);
    expect($again->id)->toBe($invitation->id)
        ->and(GroupInvitation::query()->count())->toBe(1);
    Notification::assertSentToTimes($invitee, GroupInvitationReceived::class, 1);
});

test('an existing member cannot be invited', function () {
    $group = Group::factory()->create();
    $owner = invitationOwnerOf($group);
    $member = User::factory()->create();
    $group->members()->attach($member->id, ['role' => GroupRole::Member->value]);

    app(InviteToGroup::class)($owner, $group, $member);
})->throws(DomainException::class);

test('you cannot invite yourself', function () {
    $group = Group::factory()->create();
    $owner = invitationOwnerOf($group);

    app(InviteToGroup::class)($owner, $group, $owner);
})->throws(DomainException::class);

test('accepting an invitation joins the group and its chat', function () {
    Notification::fake();
    $group = Group::factory()->secret()->create();
    $owner = invitationOwnerOf($group);
    $conversation = app(EnsureGroupConversation::class)($group);
    $invitee = User::factory()->create();
    $invitation = app(InviteToGroup::class)($owner, $group, $invitee);

    app(AcceptGroupInvitation::class)($invitation);

    expect($group->hasMember($invitee))->toBeTrue()
        ->and($conversation->hasParticipant($invitee))->toBeTrue()
        ->and(GroupInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse();
});

test('declining an invitation clears it without joining', function () {
    Notification::fake();
    $group = Group::factory()->secret()->create();
    $owner = invitationOwnerOf($group);
    $invitee = User::factory()->create();
    $invitation = app(InviteToGroup::class)($owner, $group, $invitee);

    app(DeclineGroupInvitation::class)($invitation);

    expect($group->hasMember($invitee))->toBeFalse()
        ->and(GroupInvitation::query()->count())->toBe(0);
});

test('a plain member cannot invite from the group page', function () {
    $group = Group::factory()->secret()->create();
    invitationOwnerOf($group);
    $member = User::factory()->create();
    $group->members()->attach($member->id, ['role' => GroupRole::Member->value]);
    $target = User::factory()->create();

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->call('invite', $target->id)
        ->assertForbidden();

    expect(GroupInvitation::query()->count())->toBe(0);
});

test('an invitation does not open a secret group until it is accepted', function () {
    Notification::fake();
    $group = Group::factory()->secret()->create();
    $owner = invitationOwnerOf($group);
    $invitee = User::factory()->create();
    app(InviteToGroup::class)($owner, $group, $invitee);

    // Still invisible while the invitation is pending — only membership opens it.
    expect($invitee->can('view', $group))->toBeFalse()
        ->and(Group::query()->visibleTo($invitee)->whereKey($group->id)->exists())->toBeFalse();

    Livewire::actingAs($invitee)
        ->test('pages::groups')
        ->call('acceptInvitation', GroupInvitation::query()->firstOrFail()->id);

    expect($invitee->fresh()->can('view', $group))->toBeTrue()
        ->and(Group::query()->visibleTo($invitee)->whereKey($group->id)->exists())->toBeTrue();
});

test('only the invitee can answer an invitation', function () {
    Notification::fake();
    $group = Group::factory()->secret()->create();
    $owner = invitationOwnerOf($group);
    $invitee = User::factory()->create();
    $meddler = User::factory()->create();
    $invitation = app(InviteToGroup::class)($owner, $group, $invitee);

    Livewire::actingAs($meddler)
        ->test('pages::groups')
        ->call('acceptInvitation', $invitation->id);

    expect($group->hasMember($invitee))->toBeFalse()
        ->and($group->hasMember($meddler))->toBeFalse()
        ->and(GroupInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
});
