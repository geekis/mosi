<?php

declare(strict_types=1);

use App\Actions\Chat\MarkConversationRead;
use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Actions\Mentions\NotifyMentions;
use App\Enums\ConversationType;
use App\Events\MessageSent;
use App\Models\User;
use App\Notifications\YouWereMentioned;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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

test('an image attachment lands in the images collection and a file in attachments', function () {
    Event::fake([MessageSent::class]);
    Storage::fake(config('media-library.disk_name'));
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    $message = app(SendMessage::class)($a, $conversation, 'sjáðu þetta', [
        UploadedFile::fake()->image('mynd.jpg'),
        UploadedFile::fake()->create('skjal.pdf', 40, 'application/pdf'),
    ]);

    expect($message->getMedia('images'))->toHaveCount(1)
        ->and($message->getMedia('attachments'))->toHaveCount(1);
});

test('a video attachment lands in the videos collection', function () {
    Event::fake([MessageSent::class]);
    Storage::fake(config('media-library.disk_name'));
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    $message = app(SendMessage::class)($a, $conversation, '', [
        UploadedFile::fake()->create('klipp.mp4', 200, 'video/mp4'),
    ]);

    expect($message->getMedia('videos'))->toHaveCount(1)
        ->and($message->getMedia('attachments'))->toHaveCount(0)
        ->and($message->getMedia('images'))->toHaveCount(0);
});

test('a message may carry only an attachment with no body', function () {
    Event::fake([MessageSent::class]);
    Storage::fake(config('media-library.disk_name'));
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    $message = app(SendMessage::class)($a, $conversation, '', [
        UploadedFile::fake()->image('mynd.png'),
    ]);

    expect($message->body)->toBe('')
        ->and($message->getMedia('images'))->toHaveCount(1);
});

test('an empty message with no attachments is rejected', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    app(SendMessage::class)($a, $conversation, '   ');
})->throws(DomainException::class);

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

test('a chat message notifies a mentioned participant', function () {
    Event::fake([MessageSent::class]);
    Notification::fake();
    $a = User::factory()->create();
    $b = User::factory()->create(['display_name' => 'Björk Guðmunds']);
    $conversation = app(StartDirectConversation::class)($a, $b);

    app(SendMessage::class)($a, $conversation, 'Halló @'.NotifyMentions::handleFor($b).'!');

    Notification::assertSentTo($b, YouWereMentioned::class);
});

test('a chat @mention never notifies someone outside the conversation', function () {
    Event::fake([MessageSent::class]);
    Notification::fake();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $outsider = User::factory()->create(['display_name' => 'Utan Hóps']);
    $conversation = app(StartDirectConversation::class)($a, $b);

    // The outsider's handle is written verbatim, but only people in the chat resolve.
    app(SendMessage::class)($a, $conversation, 'psst @'.NotifyMentions::handleFor($outsider));

    Notification::assertNothingSentTo($outsider);
});

test('a chat @mention does not notify the sender mentioning themselves', function () {
    Event::fake([MessageSent::class]);
    Notification::fake();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    app(SendMessage::class)($a, $conversation, 'note to self @'.NotifyMentions::handleFor($a));

    Notification::assertNothingSentTo($a);
});

test('only conversation participants may view a message', function () {
    Event::fake([MessageSent::class]);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    $message = app(SendMessage::class)($a, $conversation, 'leynilegt');

    expect($a->can('view', $message))->toBeTrue()
        ->and($outsider->can('view', $message))->toBeFalse();
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
