<?php

declare(strict_types=1);

use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('the chat page subscribes to the open conversation channel', function () {
    $me = User::factory()->create();
    $dm = app(StartDirectConversation::class)($me, User::factory()->create());

    $listeners = Livewire::actingAs($me)->test('pages::chat', ['conversation' => $dm->id])
        ->instance()->getListeners();

    expect($listeners)->toHaveKey("echo-private:conversation.{$dm->id},MessageSent");
});

test('the chat badge subscribes to each of the user conversations', function () {
    $me = User::factory()->create();
    $dm = app(StartDirectConversation::class)($me, User::factory()->create());

    $listeners = Livewire::actingAs($me)->test('pages::chat-nav-item')
        ->instance()->getListeners();

    expect($listeners)->toHaveKey("echo-private:conversation.{$dm->id},MessageSent")
        ->and($listeners)->toHaveKey('chat-read');
});

test('a message arriving on the open thread marks it read and pings the badge', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $dm = app(StartDirectConversation::class)($me, $other);

    $unread = fn (): int => (int) DB::table('conversation_user')
        ->where('user_id', $me->id)->where('conversation_id', $dm->id)->value('unread_count');

    // $me opens the thread (mount marks it read), then a new message arrives.
    $component = Livewire::actingAs($me)->test('pages::chat', ['conversation' => $dm->id]);
    app(SendMessage::class)($other, $dm, 'Hi there', []);
    expect($unread())->toBe(1);

    $component->call('onMessageReceived')
        ->assertDispatched('chat-read');

    expect($unread())->toBe(0);
});
