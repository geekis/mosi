<?php

declare(strict_types=1);

use App\Actions\Reactions\React;
use App\Actions\Reactions\Unreact;
use App\Enums\ReactionType;
use App\Models\Post;
use App\Models\User;

it('adds a reaction to a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    app(React::class)($user, $post, ReactionType::Love);

    expect($post->reactions()->count())->toBe(1)
        ->and($post->reactions()->first()->type)->toBe(ReactionType::Love);
});

it('changes a reaction instead of stacking', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    app(React::class)($user, $post, ReactionType::Like);
    app(React::class)($user, $post, ReactionType::Haha);

    expect($post->reactions()->count())->toBe(1)
        ->and($post->reactions()->first()->type)->toBe(ReactionType::Haha);
});

it('removes a reaction', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    app(React::class)($user, $post, ReactionType::Like);

    app(Unreact::class)($user, $post);

    expect($post->reactions()->count())->toBe(0);
});

it('records one reaction per user', function () {
    $post = Post::factory()->create();

    app(React::class)(User::factory()->create(), $post, ReactionType::Like);
    app(React::class)(User::factory()->create(), $post, ReactionType::Love);

    expect($post->reactions()->count())->toBe(2);
});

it('summarises reaction counts by type', function () {
    $post = Post::factory()->create();
    app(React::class)(User::factory()->create(), $post, ReactionType::Like);
    app(React::class)(User::factory()->create(), $post, ReactionType::Like);
    app(React::class)(User::factory()->create(), $post, ReactionType::Love);

    expect($post->reactionCounts())->toMatchArray(['like' => 2, 'love' => 1]);
});
