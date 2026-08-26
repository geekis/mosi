<?php

declare(strict_types=1);

use App\Actions\Groups\DeleteGroupMedia;
use App\Actions\Groups\UploadGroupFile;
use App\Actions\Groups\UploadGroupMedia;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function memberOf(Group $group, string $role = GroupRole::Member->value): User
{
    $user = User::factory()->create();
    $group->members()->attach($user->id, ['role' => $role]);

    return $user;
}

beforeEach(function () {
    Storage::fake('local');
});

// --- UploadGroupFile -------------------------------------------------------

test('a member can upload an allowed document to the files collection', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);

    $media = app(UploadGroupFile::class)(
        $member,
        $group,
        UploadedFile::fake()->create('handbook.pdf', 200, 'application/pdf'),
    );

    expect($media->collection_name)->toBe('files')
        ->and($media->disk)->toBe('local')
        ->and($media->getCustomProperty('uploaded_by'))->toBe($member->id)
        ->and($group->fresh()->getMedia('files'))->toHaveCount(1);
});

test('uploading a disallowed file type is rejected', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);

    app(UploadGroupFile::class)(
        $member,
        $group,
        UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
    );
})->throws(DomainException::class);

test('an oversized file is rejected', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);

    app(UploadGroupFile::class)(
        $member,
        $group,
        UploadedFile::fake()->create('huge.pdf', (UploadGroupFile::MAX_BYTES / 1024) + 1024, 'application/pdf'),
    );
})->throws(DomainException::class);

test('a non-member cannot upload a file', function () {
    $group = Group::factory()->create();
    $outsider = User::factory()->create();

    app(UploadGroupFile::class)(
        $outsider,
        $group,
        UploadedFile::fake()->create('handbook.pdf', 10, 'application/pdf'),
    );
})->throws(DomainException::class);

// --- UploadGroupMedia ------------------------------------------------------

test('a member can upload an image to the gallery', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);

    $media = app(UploadGroupMedia::class)($member, $group, UploadedFile::fake()->image('photo.jpg'));

    expect($media->collection_name)->toBe('gallery')
        ->and($media->disk)->toBe('local');
});

test('a member can upload a video to the gallery', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);

    $media = app(UploadGroupMedia::class)(
        $member,
        $group,
        UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
    );

    expect($media->collection_name)->toBe('gallery');
});

test('a non image or video is rejected from the gallery', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);

    app(UploadGroupMedia::class)(
        $member,
        $group,
        UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    );
})->throws(DomainException::class);

test('a non-member cannot upload media', function () {
    $group = Group::factory()->create();
    $outsider = User::factory()->create();

    app(UploadGroupMedia::class)($outsider, $group, UploadedFile::fake()->image('photo.jpg'));
})->throws(DomainException::class);

// --- DeleteGroupMedia ------------------------------------------------------

test('the uploader can delete their own file', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);
    $media = app(UploadGroupFile::class)($member, $group, UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));

    app(DeleteGroupMedia::class)($member, $group, $media);

    expect($group->fresh()->getMedia('files'))->toHaveCount(0);
});

test('a group manager can delete another member\'s file', function () {
    $group = Group::factory()->create();
    $owner = memberOf($group, GroupRole::Owner->value);
    $member = memberOf($group);
    $media = app(UploadGroupFile::class)($member, $group, UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));

    app(DeleteGroupMedia::class)($owner, $group, $media);

    expect($group->fresh()->getMedia('files'))->toHaveCount(0);
});

test('a plain member cannot delete another member\'s file', function () {
    $group = Group::factory()->create();
    $uploader = memberOf($group);
    $other = memberOf($group);
    $media = app(UploadGroupFile::class)($uploader, $group, UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));

    app(DeleteGroupMedia::class)($other, $group, $media);
})->throws(DomainException::class);

test('media without an uploader can only be deleted by a manager', function () {
    $group = Group::factory()->create();
    $member = memberOf($group);
    $owner = memberOf($group, GroupRole::Owner->value);

    // Simulate legacy media with no uploaded_by custom property.
    $file = UploadedFile::fake()->create('legacy.pdf', 10, 'application/pdf');
    $media = $group->addMedia($file->getRealPath())
        ->usingFileName('legacy.pdf')
        ->toMediaCollection('files');

    expect(fn () => app(DeleteGroupMedia::class)($member, $group, $media))->toThrow(DomainException::class);

    app(DeleteGroupMedia::class)($owner, $group, $media);
    expect($group->fresh()->getMedia('files'))->toHaveCount(0);
});
