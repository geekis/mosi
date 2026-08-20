<?php

declare(strict_types=1);

use App\Actions\Groups\ApproveJoinRequest;
use App\Actions\Groups\DeclineJoinRequest;
use App\Actions\Groups\JoinGroup;
use App\Actions\Groups\LeaveGroup;
use App\Actions\Groups\RequestToJoinGroup;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\User;

it('lets a user join an open group as a member', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    app(JoinGroup::class)($user, $group);

    expect($group->hasMember($user))->toBeTrue();
});

it('refuses a direct join of a closed or secret group', function (Closure $makeGroup) {
    $user = User::factory()->create();
    $group = $makeGroup();

    expect(fn () => app(JoinGroup::class)($user, $group))->toThrow(DomainException::class)
        ->and($group->hasMember($user))->toBeFalse();
})->with([
    'closed' => [fn () => Group::factory()->closed()->create()],
    'secret' => [fn () => Group::factory()->secret()->create()],
]);

it('lets a user leave a group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();
    app(JoinGroup::class)($user, $group);

    app(LeaveGroup::class)($user, $group);

    expect($group->hasMember($user))->toBeFalse();
});

it('turns an approved request to join a closed group into a membership', function () {
    $user = User::factory()->create();
    $group = Group::factory()->closed()->create();

    $request = app(RequestToJoinGroup::class)($user, $group);

    expect($group->hasMember($user))->toBeFalse()
        ->and($request->exists)->toBeTrue();

    app(ApproveJoinRequest::class)($request);

    expect($group->hasMember($user))->toBeTrue()
        ->and(GroupJoinRequest::whereKey($request->id)->exists())->toBeFalse();
});

it('declines a join request without adding a member', function () {
    $user = User::factory()->create();
    $group = Group::factory()->closed()->create();
    $request = app(RequestToJoinGroup::class)($user, $group);

    app(DeclineJoinRequest::class)($request);

    expect($group->hasMember($user))->toBeFalse()
        ->and(GroupJoinRequest::whereKey($request->id)->exists())->toBeFalse();
});

it('only accepts join requests for closed groups', function (Closure $makeGroup) {
    $user = User::factory()->create();

    expect(fn () => app(RequestToJoinGroup::class)($user, $makeGroup()))->toThrow(DomainException::class);
})->with([
    'open' => [fn () => Group::factory()->create()],
    'secret' => [fn () => Group::factory()->secret()->create()],
]);

it('does not request to join when already a member', function () {
    $user = User::factory()->create();
    $group = Group::factory()->closed()->create();
    $group->members()->attach($user, ['role' => GroupRole::Member->value]);

    expect(fn () => app(RequestToJoinGroup::class)($user, $group))->toThrow(DomainException::class);
});
