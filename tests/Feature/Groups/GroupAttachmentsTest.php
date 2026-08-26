<?php

declare(strict_types=1);

use App\Actions\Groups\UploadGroupFile;
use App\Actions\Groups\UploadGroupMedia;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake(config('media-library.disk_name'));
});

function attachmentMember(Group $group, string $role = GroupRole::Member->value): User
{
    $user = User::factory()->create();
    $group->members()->attach($user->id, ['role' => $role]);

    return $user;
}

// --- GroupPolicy::viewMemberContent ---------------------------------------

test('viewMemberContent is allowed for members and denied for non-members', function () {
    $group = Group::factory()->create();
    $member = attachmentMember($group);
    $outsider = User::factory()->create();

    expect($member->can('viewMemberContent', $group))->toBeTrue()
        ->and($outsider->can('viewMemberContent', $group))->toBeFalse();
});

test('viewMemberContent is denied to a non-member of a secret group', function () {
    $group = Group::factory()->secret()->create();
    $outsider = User::factory()->create();

    expect($outsider->can('viewMemberContent', $group))->toBeFalse();
});

// --- GroupMediaController --------------------------------------------------

test('a member downloads a file as an attachment', function () {
    $group = Group::factory()->create();
    $member = attachmentMember($group);
    $media = app(UploadGroupFile::class)($member, $group, UploadedFile::fake()->create('handbook.pdf', 50, 'application/pdf'));

    $this->actingAs($member)
        ->get(route('groups.attachments', [$group, $media]))
        ->assertOk()
        ->assertDownload('handbook.pdf');
});

test('a member views a gallery image inline', function () {
    $group = Group::factory()->create();
    $member = attachmentMember($group);
    $media = app(UploadGroupMedia::class)($member, $group, UploadedFile::fake()->image('photo.jpg'));

    $this->actingAs($member)
        ->get(route('groups.attachments', [$group, $media]))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

test('a gallery item honours HTTP range requests so video can be scrubbed', function () {
    $group = Group::factory()->create();
    $member = attachmentMember($group);
    // Real bytes are needed for a satisfiable range (fake()->create only fakes the size).
    $file = UploadedFile::fake()->createWithContent('clip.mp4', str_repeat('x', 4096));
    $media = $group->addMedia($file->getRealPath())
        ->usingFileName('clip.mp4')
        ->withCustomProperties(['uploaded_by' => $member->id])
        ->toMediaCollection('gallery');

    $this->actingAs($member)
        ->get(route('groups.attachments', [$group, $media]), ['Range' => 'bytes=0-1'])
        ->assertStatus(206)
        ->assertHeader('content-range');
});

test('a non-member is forbidden from a group attachment', function () {
    $group = Group::factory()->create();
    $member = attachmentMember($group);
    $outsider = User::factory()->create();
    $media = app(UploadGroupFile::class)($member, $group, UploadedFile::fake()->create('handbook.pdf', 10, 'application/pdf'));

    $this->actingAs($outsider)
        ->get(route('groups.attachments', [$group, $media]))
        ->assertForbidden();
});

test('an attachment cannot be fetched through another group\'s url', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $member = attachmentMember($groupA);
    attachmentMember($groupB, GroupRole::Owner->value); // ensure B has a member too
    $media = app(UploadGroupFile::class)($member, $groupA, UploadedFile::fake()->create('handbook.pdf', 10, 'application/pdf'));

    $this->actingAs($member)
        ->get(route('groups.attachments', [$groupB, $media]))
        ->assertNotFound();
});

test('the public banner cannot be fetched through the attachments route', function () {
    $group = Group::factory()->create();
    $member = attachmentMember($group);
    $bannerFile = UploadedFile::fake()->image('banner.jpg', 1200, 400);
    $banner = $group->addMedia($bannerFile->getRealPath())
        ->usingFileName('banner.jpg')
        ->toMediaCollection('banner');

    $this->actingAs($member)
        ->get(route('groups.attachments', [$group, $banner]))
        ->assertNotFound();
});
