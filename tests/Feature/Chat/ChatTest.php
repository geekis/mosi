<?php

declare(strict_types=1);

use App\Actions\Chat\MarkConversationRead;
use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Enums\ConversationType;
use App\Events\MessageSent;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('starting a direct conversation is idempotent', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $first = app(StartDirectConversation::class)($a, $b);
    $second = app(StartDirectConversation::class)($b, $a);

    expect($first->id)->toBe($second->id)
        ->and($first->type)->toBe(ConversationType::Direct)
        ->and($first->participants()->count())->toBe(2);
});

test('you cannot start a conversation with yourself', function () {
    $a = User::factory()->create();
    app(StartDirectConversation::class)($a, $a);
})->throws(DomainException::class);

test('sending a message persists it and bumps only the other participant unread count', function () {
    Event::fake([MessageSent::class]);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    app(SendMessage::class)($a, $conversation, 'Halló');

    $aPivot = $conversation->participants()->find($a->id)->pivot;
    $bPivot = $conversation->participants()->find($b->id)->pivot;

    expect($conversation->messages()->count())->toBe(1)
        ->and($aPivot->unread_count)->toBe(0)
        ->and($bPivot->unread_count)->toBe(1)
        ->and($conversation->fresh()->last_message_at)->not->toBeNull();

    Event::assertDispatched(MessageSent::class);
});

test('a non-participant cannot send a message', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    app(SendMessage::class)($outsider, $conversation, 'sneaking in');
})->throws(DomainException::class);

test('marking a conversation read zeroes the unread count', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);
    app(SendMessage::class)($a, $conversation, 'ping');

    app(MarkConversationRead::class)($b, $conversation);

    expect($conversation->participants()->find($b->id)->pivot->unread_count)->toBe(0);
});

test('only participants are authorised on a conversation (channel + policy)', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    // The private-channel callback (routes/channels.php) and ConversationPolicy both
    // gate on hasParticipant — the single predicate that keeps a thread private.
    expect($conversation->hasParticipant($a))->toBeTrue()
        ->and($conversation->hasParticipant($outsider))->toBeFalse()
        ->and($a->can('view', $conversation))->toBeTrue()
        ->and($outsider->can('view', $conversation))->toBeFalse();
});
