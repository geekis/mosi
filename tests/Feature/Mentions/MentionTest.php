<?php

declare(strict_types=1);

use App\Actions\Comments\AddComment;
use App\Actions\Mentions\NotifyMentions;
use App\Actions\Posts\CreatePost;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Mention;
use App\Models\Post;
use App\Models\User;
use App\Notifications\YouWereMentioned;
use Illuminate\Support\Facades\Notification;

function mentionBody(User $user): string
{
    // A plain-text mention: the user's handle is their display name without spaces.
    return 'Hi @'.NotifyMentions::handleFor($user).', welcome!';
}

it('notifies a mentioned user and records the mention', function () {
    Notification::fake();
    $author = User::factory()->create();
    $mentioned = User::factory()->create();

    $post = app(CreatePost::class)($author, ['body' => mentionBody($mentioned)]);

    Notification::assertSentTo($mentioned, YouWereMentioned::class);

    expect(Mention::query()
        ->where('mentionable_type', $post->getMorphClass())
        ->where('mentionable_id', $post->id)
        ->where('user_id', $mentioned->id)
        ->exists())->toBeTrue();
});

it('does not notify a self-mention', function () {
    Notification::fake();
    $author = User::factory()->create();

    app(CreatePost::class)($author, ['body' => mentionBody($author)]);

    Notification::assertNothingSentTo($author);
});

it('never notifies a mention a user cannot see in a secret group', function () {
    Notification::fake();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($member, ['role' => GroupRole::Member->value]);

    app(CreatePost::class)($member, ['body' => mentionBody($outsider), 'group_id' => $secret->id]);

    Notification::assertNothingSentTo($outsider);
    expect(Mention::query()->where('user_id', $outsider->id)->exists())->toBeFalse();
});

it('notifies mentions in comments', function () {
    Notification::fake();
    $author = User::factory()->create();
    $mentioned = User::factory()->create();
    $post = Post::factory()->create();

    app(AddComment::class)($author, $post, mentionBody($mentioned));

    Notification::assertSentTo($mentioned, YouWereMentioned::class);
});

it('resolves a @handle written as the display name without spaces', function () {
    Notification::fake();
    $author = User::factory()->create();
    $egill = User::factory()->create(['display_name' => 'Egill Heinesen']);

    app(CreatePost::class)($author, ['body' => 'Sæll @EgillHeinesen, gaman að sjá þig!']);

    Notification::assertSentTo($egill, YouWereMentioned::class);
});

it('matches a handle case-insensitively', function () {
    Notification::fake();
    $author = User::factory()->create();
    $egill = User::factory()->create(['display_name' => 'Egill Heinesen']);

    app(CreatePost::class)($author, ['body' => 'hallo @egillheinesen']);

    Notification::assertSentTo($egill, YouWereMentioned::class);
});
