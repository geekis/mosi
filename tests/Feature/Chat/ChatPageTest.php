<?php

declare(strict_types=1);

use App\Actions\Chat\EnsureGroupConversation;
use App\Actions\Chat\MarkConversationRead;
use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Actions\Mentions\NotifyMentions;
use App\Enums\GroupRole;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Fake only the broadcast — a bare Event::fake() would also swallow the model events
// Spatie sluggable relies on, leaving Group::factory() with a null slug.
beforeEach(fn () => Event::fake([MessageSent::class]));

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

test('the chat page sends an image attachment', function () {
    Storage::fake(config('media-library.disk_name'));
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);

    Livewire::actingAs($a)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->set('attachments', [UploadedFile::fake()->image('mynd.jpg')])
        ->call('send')
        ->assertSet('attachments', []);

    expect($conversation->messages()->first()?->getMedia('images'))->toHaveCount(1);
});

test('a direct message shows a read receipt once the other person reads', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $conversation = app(StartDirectConversation::class)($a, $b);
    app(SendMessage::class)($a, $conversation, 'lestu þetta');

    // Before b reads there is no receipt.
    Livewire::actingAs($a)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->assertViewHas('seenBy', fn ($seenBy) => $seenBy->isEmpty());

    app(MarkConversationRead::class)($b, $conversation);

    Livewire::actingAs($a)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->assertViewHas('seenBy', fn ($seenBy) => $seenBy->count() === 1)
        ->assertSee('Séð');
});

test('a group message shows how many others have seen it', function () {
    $me = User::factory()->create();
    $others = User::factory()->count(2)->create();
    $group = Group::factory()->create();
    $group->members()->attach(
        $others->concat([$me])->mapWithKeys(fn (User $u) => [$u->id => ['role' => GroupRole::Member->value]])->all()
    );
    $conversation = app(EnsureGroupConversation::class)($group);
    app(SendMessage::class)($me, $conversation, 'halló hópur');

    foreach ($others as $other) {
        app(MarkConversationRead::class)($other, $conversation);
    }

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->assertViewHas('seenBy', fn ($seenBy) => $seenBy->count() === 2)
        ->assertSee('Séð af 2');
});

test('the composer only offers the other people in the thread for @mention', function () {
    $me = User::factory()->create();
    $mate = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $mate->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        // Only the co-participant is suggested — never me, never someone outside the thread.
        ->assertViewHas('mentionables', fn ($list) => collect($list)->pluck('handle')->all()
            === [NotifyMentions::handleFor($mate)]);
});

test('a chat message renders an @mention of a participant as a highlighted handle', function () {
    $me = User::factory()->create();
    $mate = User::factory()->create(['display_name' => 'Björk Guðmunds']);
    $conversation = app(StartDirectConversation::class)($me, $mate);
    app(SendMessage::class)($mate, $conversation, 'Sæl @'.NotifyMentions::handleFor($me).'!');

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->assertSeeHtml('<span class="font-semibold">@'.NotifyMentions::handleFor($me).'</span>');
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

test('the group chat lists its members and a message button', function () {
    $me = User::factory()->create();
    $mate = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $mate->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->assertSee('Meðlimir')
        ->assertSee($mate->display_name)
        ->assertSee('Þú');
});

test('a group member can start a direct chat with another member from the roster', function () {
    $me = User::factory()->create();
    $mate = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $mate->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->call('startChatWith', $mate->id)
        ->assertSet('conversation', fn ($id) => $id !== $conversation->id);

    $dm = Conversation::query()->where('type', 'direct')->first();
    expect($dm)->not->toBeNull()
        ->and($dm->hasParticipant($me))->toBeTrue()
        ->and($dm->hasParticipant($mate))->toBeTrue();
});

test('starting a chat with yourself from the roster is a no-op', function () {
    $me = User::factory()->create();
    $mate = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $mate->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->call('startChatWith', $me->id)
        ->assertSet('conversation', $conversation->id);

    expect(Conversation::query()->where('type', 'direct')->count())->toBe(0);
});

test('you cannot start a chat with someone outside the current thread', function () {
    $me = User::factory()->create();
    $mate = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $me->id => ['role' => GroupRole::Member->value],
        $mate->id => ['role' => GroupRole::Member->value],
    ]);
    $conversation = app(EnsureGroupConversation::class)($group);

    Livewire::actingAs($me)
        ->test('pages::chat', ['conversation' => $conversation->id])
        ->call('startChatWith', $outsider->id)
        ->assertSet('conversation', $conversation->id);

    expect(Conversation::query()->where('type', 'direct')->count())->toBe(0);
});

test('the profile message button starts a dm', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Livewire::actingAs($a)
        ->test('pages::people.show', ['user' => $b])
        ->call('message');

    expect(Conversation::query()->count())->toBe(1);
});
