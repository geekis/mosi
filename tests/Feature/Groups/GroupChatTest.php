<?php

declare(strict_types=1);

use App\Actions\Chat\EnsureGroupConversation;
use App\Actions\Groups\JoinGroup;
use App\Actions\Groups\LeaveGroup;
use App\Enums\ConversationType;
use App\Enums\GroupRole;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;

function groupWithMembers(int $count = 2): array
{
    $group = Group::factory()->create();
    $members = User::factory()->count($count)->create();
    $group->members()->attach(
        $members->mapWithKeys(fn (User $m) => [$m->id => ['role' => GroupRole::Member->value]])->all()
    );

    return [$group, $members];
}

test('a group chat is created once and includes every member', function () {
    [$group, $members] = groupWithMembers(3);

    $conversation = app(EnsureGroupConversation::class)($group);

    expect($conversation->type)->toBe(ConversationType::Group)
        ->and($conversation->group_id)->toBe($group->id)
        ->and($conversation->title)->toBe($group->name);

    foreach ($members as $member) {
        expect($conversation->hasParticipant($member))->toBeTrue();
    }

    // Idempotent: calling again returns the same conversation, never a duplicate.
    $again = app(EnsureGroupConversation::class)($group);
    expect($again->id)->toBe($conversation->id)
        ->and(Conversation::where('group_id', $group->id)->count())->toBe(1);
});

test('syncing the chat adds new members and drops those who left', function () {
    [$group, $members] = groupWithMembers(2);
    [$stay, $leaver] = [$members[0], $members[1]];

    $conversation = app(EnsureGroupConversation::class)($group);

    $joiner = User::factory()->create();
    $group->members()->attach($joiner->id, ['role' => GroupRole::Member->value]);
    $group->members()->detach($leaver->id);

    app(EnsureGroupConversation::class)($group);

    expect($conversation->hasParticipant($stay))->toBeTrue()
        ->and($conversation->hasParticipant($joiner))->toBeTrue()
        ->and($conversation->hasParticipant($leaver))->toBeFalse();
});

test('re-syncing preserves an existing participant read state', function () {
    [$group, $members] = groupWithMembers(2);
    $conversation = app(EnsureGroupConversation::class)($group);
    $conversation->participants()->updateExistingPivot($members[0]->id, ['unread_count' => 4]);

    // A new member joining triggers a re-sync.
    $joiner = User::factory()->create();
    $group->members()->attach($joiner->id, ['role' => GroupRole::Member->value]);
    app(EnsureGroupConversation::class)($group);

    $pivot = $conversation->participants()->whereKey($members[0]->id)->first()->pivot;
    expect((int) $pivot->unread_count)->toBe(4);
});

test('a member opens the group chat from the group page', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    $group->members()->attach($member->id, ['role' => GroupRole::Member->value]);

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->call('openChat')
        ->assertRedirect();

    $conversation = $group->conversation()->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->hasParticipant($member))->toBeTrue();
});

test('a non-member cannot open the group chat', function () {
    $group = Group::factory()->create();
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::groups.show', ['group' => $group])
        ->call('openChat')
        ->assertNoRedirect();

    expect($group->conversation()->first())->toBeNull();
});

test('joining a group with an existing chat adds you to it', function () {
    [$group] = groupWithMembers(1);
    $conversation = app(EnsureGroupConversation::class)($group);

    $newbie = User::factory()->create();
    app(JoinGroup::class)($newbie, $group);

    expect($conversation->hasParticipant($newbie))->toBeTrue();
});

test('leaving a group removes you from its chat', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    $group->members()->attach($member->id, ['role' => GroupRole::Member->value]);
    $conversation = app(EnsureGroupConversation::class)($group);

    app(LeaveGroup::class)($member, $group);

    expect($conversation->hasParticipant($member))->toBeFalse();
});
