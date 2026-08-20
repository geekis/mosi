<?php

declare(strict_types=1);

use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(fn () => Event::fake());

test('the chat page sends a message and clears the input', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    Livewire::actingAs($a)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->set('body', 'Sæll félagi')
        ->call('send')
        ->assertSet('body', '');

    expect($conversation->messages()->where('body', 'Sæll félagi')->exists())->toBeTrue();
});

test('opening a conversation marks it read', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);
    app(SendMessage::class)($a, $conversation, 'unread ping');

    Livewire::actingAs($b)->test('pages::chat', ['conversation' => $conversation->id]);

    expect($conversation->participants()->find($b->id)->pivot->unread_count)->toBe(0);
});

test('a user cannot open a conversation they are not part of', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    Livewire::actingAs($outsider)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->assertViewHas('active', null);
});

test('the profile message button starts a dm', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Livewire::actingAs($a)
        ->test('pages::people.show', ['user' => $b])
        ->call('message');

    expect(Conversation::query()->count())->toBe(1);
});
