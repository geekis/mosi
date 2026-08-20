<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\User;

it('stores a translatable name and description and derives a slug', function () {
    $group = Group::factory()->create([
        'name' => ['is' => 'Gönguhópurinn', 'en' => 'Hiking group'],
    ]);

    expect($group->slug)->toBe('gonguhopurinn')
        ->and($group->type)->toBe(GroupType::Interest);

    app()->setLocale('is');
    expect($group->name)->toBe('Gönguhópurinn');

    app()->setLocale('en');
    expect($group->name)->toBe('Hiking group');
});

describe('secret group visibility', function () {
    it('hides a secret group from non-members in the visibility scope', function () {
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $secret = Group::factory()->secret()->create();
        $secret->members()->attach($member, ['role' => GroupRole::Member->value]);

        expect(Group::query()->visibleTo($member)->pluck('id'))->toContain($secret->id)
            ->and(Group::query()->visibleTo($outsider)->pluck('id'))->not->toContain($secret->id);
    });

    it('enforces the same rule in the policy', function () {
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $secret = Group::factory()->secret()->create();
        $secret->members()->attach($member, ['role' => GroupRole::Member->value]);

        expect($member->can('view', $secret))->toBeTrue()
            ->and($outsider->can('view', $secret))->toBeFalse();
    });
});

it('shows open and closed groups to everyone in the scope', function () {
    $user = User::factory()->create();
    $open = Group::factory()->create();
    $closed = Group::factory()->closed()->create();

    $visible = Group::query()->visibleTo($user)->pluck('id');

    expect($visible)->toContain($open->id)
        ->and($visible)->toContain($closed->id)
        ->and($open->visibility)->toBe(GroupVisibility::Open);
});
