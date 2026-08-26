<?php

declare(strict_types=1);

use App\Actions\Chat\EnsureGroupConversation;
use App\Actions\Chat\MarkConversationRead;
use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;

test('the chat nav badge counts unread direct messages to me', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $dm = app(StartDirectConversation::class)($other, $me);

    app(SendMessage::class)($other, $dm, 'hæ');

    Livewire::actingAs($me)
        ->test('pages::chat-nav-item')
        ->assertSet('unread', 1);
});

test('the chat nav badge counts unread messages from a group I belong to', function () {
    $me = User::factory()->create();
    $sender = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $sender->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);

    app(SendMessage::class)($sender, $conversation, 'fyrsta');
    app(SendMessage::class)($sender, $conversation, 'önnur');

    Livewire::actingAs($me)
        ->test('pages::chat-nav-item')
        ->assertSet('unread', 2);
});

test('the chat nav badge sums unread across direct and group threads', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $other->id => ['role' => GroupRole::Member->value],
    ]);

    $dm = app(StartDirectConversation::class)($other, $me);
    app(SendMessage::class)($other, $dm, 'dm');

    $groupConversation = app(EnsureGroupConversation::class)($group);
    app(SendMessage::class)($other, $groupConversation, 'group');

    Livewire::actingAs($me)
        ->test('pages::chat-nav-item')
        ->assertSet('unread', 2);
});

test('the sender does not see their own message as unread', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $dm = app(StartDirectConversation::class)($me, $other);

    app(SendMessage::class)($me, $dm, 'from me');

    Livewire::actingAs($me)
        ->test('pages::chat-nav-item')
        ->assertSet('unread', 0);
});

test('reading a conversation clears the badge', function () {
    $me = User::factory()->create();
    $sender = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $sender->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);
    app(SendMessage::class)($sender, $conversation, 'ólesið');

    app(MarkConversationRead::class)($me, $conversation);

    Livewire::actingAs($me)
        ->test('pages::chat-nav-item')
        ->assertSet('unread', 0);
});

test('opening the chat page dispatches a read event to refresh the badge', function () {
    $me = User::factory()->create();
    $sender = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $sender->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);
    app(SendMessage::class)($sender, $conversation, 'nýtt');

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->assertDispatched('chat-read');
});
