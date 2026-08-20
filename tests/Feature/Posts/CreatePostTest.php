<?php

declare(strict_types=1);

use App\Actions\Posts\CreatePost;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

it('creates a personal post published immediately', function () {
    $user = User::factory()->create();

    $post = app(CreatePost::class)($user, ['body' => '<p>Halló allir</p>']);

    expect($post->user_id)->toBe($user->id)
        ->and($post->group_id)->toBeNull()
        ->and($post->isPublished())->toBeTrue();
});

it('requires group membership to post in a group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    expect(fn () => app(CreatePost::class)($user, ['body' => 'x', 'group_id' => $group->id]))
        ->toThrow(DomainException::class);
});

it('lets a member post in their group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach($user, ['role' => GroupRole::Member->value]);

    $post = app(CreatePost::class)($user, ['body' => 'x', 'group_id' => $group->id]);

    expect($post->group_id)->toBe($group->id);
});

it('honours the official flag only for comms', function () {
    Role::findOrCreate('comms', 'web');
    $comms = User::factory()->create();
    $comms->load('roles');
    $comms->assignRole('comms');
    $regular = User::factory()->create();

    expect(app(CreatePost::class)($comms, ['body' => 'x', 'is_official' => true])->is_official)->toBeTrue()
        ->and(app(CreatePost::class)($regular, ['body' => 'x', 'is_official' => true])->is_official)->toBeFalse();
});

it('creates a draft when published_at is null', function () {
    $user = User::factory()->create();

    $post = app(CreatePost::class)($user, ['body' => 'x', 'published_at' => null]);

    expect($post->published_at)->toBeNull()
        ->and($post->isPublished())->toBeFalse();
});

it('attaches a file to a post', function () {
    Storage::fake('public');
    $post = Post::factory()->create();

    $post->addMedia(UploadedFile::fake()->create('report.pdf', 120))->toMediaCollection('attachments');

    expect($post->getMedia('attachments'))->toHaveCount(1);
});
