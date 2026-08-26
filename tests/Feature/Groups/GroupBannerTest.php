<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function bannerGroupMember(Group $group): User
{
    $user = User::factory()->create();
    $group->members()->attach($user->id, ['role' => GroupRole::Member->value]);

    return $user;
}

test('a group without a banner shows a placeholder', function () {
    $group = Group::factory()->create();
    $member = bannerGroupMember($group);

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->assertSeeHtml('data-group-banner="placeholder"')
        ->assertDontSeeHtml('data-group-banner="image"');
});

test('a group with a banner shows the banner image, not the placeholder', function () {
    Storage::fake(config('media-library.disk_name'));
    $group = Group::factory()->create();
    $member = bannerGroupMember($group);

    $bannerFile = UploadedFile::fake()->image('banner.jpg', 1200, 400);
    $group->addMedia($bannerFile->getRealPath())
        ->usingFileName('banner.jpg')
        ->toMediaCollection('banner');

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->assertSeeHtml('data-group-banner="image"')
        ->assertDontSeeHtml('data-group-banner="placeholder"');
});
