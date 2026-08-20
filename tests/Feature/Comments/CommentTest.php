<?php

declare(strict_types=1);

use App\Actions\Comments\AddComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Reactions\React;
use App\Enums\GroupRole;
use App\Enums\ReactionType;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;

it('adds a comment to a post', function () {
    $comment = app(AddComment::class)(User::factory()->create(), Post::factory()->create(), 'Frábært!');

    expect($comment->isReply())->toBeFalse();
});

it('adds a one-level reply', function () {
    $post = Post::factory()->create();
    $comment = app(AddComment::class)(User::factory()->create(), $post, 'top');

    $reply = app(AddComment::class)(User::factory()->create(), $post, 'reply', $comment);

    expect($reply->parent_id)->toBe($comment->id)
        ->and($reply->isReply())->toBeTrue()
        ->and($comment->replies()->count())->toBe(1);
});

it('does not allow replies deeper than one level', function () {
    $post = Post::factory()->create();
    $comment = app(AddComment::class)(User::factory()->create(), $post, 'top');
    $reply = app(AddComment::class)(User::factory()->create(), $post, 'reply', $comment);

    expect(fn () => app(AddComment::class)(User::factory()->create(), $post, 'nested', $reply))
        ->toThrow(DomainException::class);
});

it('rejects a reply whose parent is on another post', function () {
    $post = Post::factory()->create();
    $comment = app(AddComment::class)(User::factory()->create(), Post::factory()->create(), 'top');

    expect(fn () => app(AddComment::class)(User::factory()->create(), $post, 'reply', $comment))
        ->toThrow(DomainException::class);
});

it('soft-deletes a comment', function () {
    $comment = Comment::factory()->create();

    app(DeleteComment::class)($comment);

    expect(Comment::query()->count())->toBe(0)
        ->and(Comment::withTrashed()->count())->toBe(1);
});

it('supports reactions on comments', function () {
    $comment = Comment::factory()->create();

    app(React::class)(User::factory()->create(), $comment, ReactionType::Love);

    expect($comment->reactions()->count())->toBe(1);
});

it('never leaks a comment on a secret-group post to a non-member', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($member, ['role' => GroupRole::Member->value]);

    $post = Post::factory()->inGroup($secret)->create(['user_id' => $member->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $member->id]);

    expect($member->can('view', $comment))->toBeTrue()
        ->and($outsider->can('view', $comment))->toBeFalse();
});
