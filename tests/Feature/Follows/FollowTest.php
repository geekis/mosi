<?php

declare(strict_types=1);

use App\Actions\Follows\FollowUser;
use App\Actions\Follows\UnfollowUser;
use App\Models\User;

it('follows another user', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    app(FollowUser::class)($follower, $followed);

    expect($follower->isFollowing($followed))->toBeTrue()
        ->and($followed->followers()->whereKey($follower->id)->exists())->toBeTrue();
});

it('cannot follow itself', function () {
    $user = User::factory()->create();

    expect(fn () => app(FollowUser::class)($user, $user))->toThrow(DomainException::class)
        ->and($user->isFollowing($user))->toBeFalse();
});

it('following is idempotent', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    app(FollowUser::class)($follower, $followed);
    app(FollowUser::class)($follower, $followed);

    expect($follower->following()->count())->toBe(1);
});

it('unfollows a user', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create();
    app(FollowUser::class)($follower, $followed);

    app(UnfollowUser::class)($follower, $followed);

    expect($follower->isFollowing($followed))->toBeFalse();
});
