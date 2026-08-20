<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;

it('never leaks a secret group post to a non-member', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();

    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($member, ['role' => GroupRole::Member->value]);
    $post = Post::factory()->inGroup($secret)->create(['user_id' => $member->id]);

    expect(Post::query()->visibleTo($member)->pluck('id'))->toContain($post->id)
        ->and(Post::query()->visibleTo($outsider)->pluck('id'))->not->toContain($post->id)
        ->and($member->can('view', $post))->toBeTrue()
        ->and($outsider->can('view', $post))->toBeFalse();
});

it('shows open-group and personal posts to everyone', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $open = Group::factory()->create();

    $groupPost = Post::factory()->inGroup($open)->create(['user_id' => $author->id]);
    $personalPost = Post::factory()->create(['user_id' => $author->id]);

    $visible = Post::query()->visibleTo($viewer)->pluck('id');

    expect($visible)->toContain($groupPost->id)
        ->and($visible)->toContain($personalPost->id);
});

it('hides a draft from everyone but its author', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $draft = Post::factory()->draft()->create(['user_id' => $author->id]);

    expect(Post::query()->visibleTo($author)->pluck('id'))->toContain($draft->id)
        ->and(Post::query()->visibleTo($viewer)->pluck('id'))->not->toContain($draft->id);
});

it('keeps a scheduled post out of the published scope until its time', function () {
    $viewer = User::factory()->create();
    $scheduled = Post::factory()->scheduled()->create();

    expect(Post::query()->published()->pluck('id'))->not->toContain($scheduled->id)
        ->and(Post::query()->visibleTo($viewer)->pluck('id'))->not->toContain($scheduled->id);
});
