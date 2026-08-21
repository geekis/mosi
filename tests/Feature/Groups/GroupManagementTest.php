<?php

declare(strict_types=1);

use App\Actions\Groups\UpdateGroupBanner;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function ownerOf(Group $group): User
{
    $owner = User::factory()->create();
    $group->members()->attach($owner->id, ['role' => GroupRole::Owner->value]);

    return $owner;
}

test('an owner can edit the group name and description', function () {
    $group = Group::factory()->create();
    $owner = ownerOf($group);

    Livewire::actingAs($owner)
        ->test('pages::groups.show', ['group' => $group])
        ->call('openEdit')
        ->set('name_is', 'Nýtt heiti')
        ->set('name_en', 'New name')
        ->set('description_is', 'Ný lýsing')
        ->set('description_en', 'New description')
        ->call('saveGroup');

    $group->refresh();
    expect($group->getTranslation('name', 'is', false))->toBe('Nýtt heiti')
        ->and($group->getTranslation('name', 'en', false))->toBe('New name')
        ->and($group->getTranslation('description', 'en', false))->toBe('New description');
});

test('renaming a group does not change its slug', function () {
    $group = Group::factory()->create(['name' => ['is' => 'Gamli', 'en' => 'Old']]);
    $owner = ownerOf($group);
    $originalSlug = $group->slug;

    Livewire::actingAs($owner)
        ->test('pages::groups.show', ['group' => $group])
        ->call('openEdit')
        ->set('name_is', 'Alveg nýtt')
        ->set('name_en', 'Totally new')
        ->call('saveGroup');

    expect($group->fresh()->slug)->toBe($originalSlug);
});

test('a plain member cannot edit the group', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    $group->members()->attach($member->id, ['role' => GroupRole::Member->value]);

    expect($member->can('update', $group))->toBeFalse();

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->call('saveGroup')
        ->assertForbidden();
});

test('an owner approves a join request, adding the requester as a member', function () {
    $group = Group::factory()->closed()->create();
    $owner = ownerOf($group);
    $requester = User::factory()->create();
    $request = GroupJoinRequest::create(['group_id' => $group->id, 'user_id' => $requester->id]);

    Livewire::actingAs($owner)
        ->test('pages::groups.show', ['group' => $group])
        ->call('approveRequest', $request->id);

    expect($group->hasMember($requester))->toBeTrue()
        ->and(GroupJoinRequest::whereKey($request->id)->exists())->toBeFalse();
});

test('an owner declines a join request without adding a member', function () {
    $group = Group::factory()->closed()->create();
    $owner = ownerOf($group);
    $requester = User::factory()->create();
    $request = GroupJoinRequest::create(['group_id' => $group->id, 'user_id' => $requester->id]);

    Livewire::actingAs($owner)
        ->test('pages::groups.show', ['group' => $group])
        ->call('declineRequest', $request->id);

    expect($group->hasMember($requester))->toBeFalse()
        ->and(GroupJoinRequest::whereKey($request->id)->exists())->toBeFalse();
});

test('a plain member cannot approve a join request', function () {
    $group = Group::factory()->closed()->create();
    $member = User::factory()->create();
    $group->members()->attach($member->id, ['role' => GroupRole::Member->value]);
    $requester = User::factory()->create();
    $request = GroupJoinRequest::create(['group_id' => $group->id, 'user_id' => $requester->id]);

    expect($member->can('manageJoinRequests', $group))->toBeFalse();

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->call('approveRequest', $request->id)
        ->assertForbidden();

    expect($group->hasMember($requester))->toBeFalse();
});

test('a moderator can manage join requests', function () {
    $group = Group::factory()->closed()->create();
    $moderator = User::factory()->create();
    $group->members()->attach($moderator->id, ['role' => GroupRole::Moderator->value]);

    expect($moderator->can('manageJoinRequests', $group))->toBeTrue()
        ->and($moderator->can('update', $group))->toBeTrue();
});

test('a group banner is stored in the banner collection', function () {
    Storage::fake(config('media-library.disk_name'));
    $group = Group::factory()->create();

    app(UpdateGroupBanner::class)($group, UploadedFile::fake()->image('banner.jpg', 1200, 400));

    expect($group->fresh()->getFirstMedia('banner'))->not->toBeNull();
});
