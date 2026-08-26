<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the sidebar lists the groups the user belongs to', function () {
    $user = User::factory()->create();
    $mine = Group::factory()->create(['name' => ['en' => 'Trail Crew', 'is' => 'Stígahópur']]);
    $mine->members()->attach($user->id, ['role' => GroupRole::Member->value]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee($mine->name);
});

test('the sidebar shows only the user own groups, never one they have not joined', function () {
    $user = User::factory()->create();
    $other = Group::factory()->create(['name' => ['en' => 'Other Crew', 'is' => 'Annar hópur']]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertDontSee($other->name);
});

test('a group with an avatar shows its image as the sidebar icon', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();
    $group = Group::factory()->create(['name' => ['en' => 'Trail Crew', 'is' => 'Stígahópur']]);
    $group->members()->attach($user->id, ['role' => GroupRole::Member->value]);

    $avatar = UploadedFile::fake()->image('avatar.jpg', 200, 200);
    $group->addMedia($avatar->getRealPath())
        ->usingFileName('avatar.jpg')
        ->toMediaCollection('avatar');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee($group->avatarUrl(), false);
});

test('the sidebar never leaks a secret group to a non-member', function () {
    $user = User::factory()->create();
    $secret = Group::factory()->secret()->create(['name' => ['en' => 'Inner Circle', 'is' => 'Innsti hringur']]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertDontSee($secret->name);
});
