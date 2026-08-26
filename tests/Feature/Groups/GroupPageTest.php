<?php

declare(strict_types=1);

use App\Actions\Reactions\React;
use App\Enums\GroupRole;
use App\Enums\ReactionType;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the group index lists a visible group and hides a secret one from a non-member', function () {
    $user = User::factory()->create();
    $open = Group::factory()->create(['name' => ['en' => 'Trail Crew', 'is' => 'Stígahópur']]);
    $secret = Group::factory()->secret()->create(['name' => ['en' => 'Inner Circle', 'is' => 'Innsti hringur']]);

    Livewire::actingAs($user)
        ->test('pages::groups')
        ->assertSee($open->name)
        ->assertDontSee($secret->name);
});

test('an open group page renders for any user and shows its published posts', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach($author->id, ['role' => GroupRole::Member->value]);
    Post::factory()->inGroup($group)->create(['user_id' => $author->id, 'body' => 'GROUP-POST-BODY']);

    Livewire::actingAs($viewer)
        ->test('pages::groups.show', ['group' => $group])
        ->assertOk()
        ->assertSee($group->name)
        ->assertSee('GROUP-POST-BODY');
});

test('a group post with an image, a reaction and a comment renders without a lazy-load error', function () {
    Storage::fake(config('media-library.disk_name'));
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach($author->id, ['role' => GroupRole::Member->value]);

    $post = Post::factory()->inGroup($group)->create(['user_id' => $author->id, 'body' => 'RICH-GROUP-POST']);
    $post->addMedia(UploadedFile::fake()->image('mynd.jpg'))->toMediaCollection('images');
    app(React::class)($author, $post, ReactionType::Love);
    Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id, 'body' => 'FYRSTA-ATHUGASEMD']);

    Livewire::actingAs($viewer)
        ->test('pages::groups.show', ['group' => $group])
        ->assertOk()
        ->assertSee('RICH-GROUP-POST')
        ->assertSee('FYRSTA-ATHUGASEMD');
});

test('a secret group page returns 404 for a non-member', function () {
    $outsider = User::factory()->create();
    $secret = Group::factory()->secret()->create();

    $this->actingAs($outsider)
        ->get(route('groups.show', $secret))
        ->assertNotFound();
});

test('a secret group page renders for a member', function () {
    $member = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($member->id, ['role' => GroupRole::Member->value]);

    $this->actingAs($member)
        ->get(route('groups.show', $secret))
        ->assertOk();
});

test('a secret group post never leaks into another group page', function () {
    $outsider = User::factory()->create();
    $author = User::factory()->create();
    $open = Group::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($author->id, ['role' => GroupRole::Member->value]);
    Post::factory()->inGroup($secret)->create(['user_id' => $author->id, 'body' => 'SECRET-GROUP-POST']);

    Livewire::actingAs($outsider)
        ->test('pages::groups.show', ['group' => $open])
        ->assertDontSee('SECRET-GROUP-POST');
});

test('joining an open group from the page adds the user as a member', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::groups.show', ['group' => $group])
        ->call('join');

    expect($group->hasMember($user))->toBeTrue();
});

test('a member can post to the group from the page', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach($user->id, ['role' => GroupRole::Member->value]);

    Livewire::actingAs($user)
        ->test('pages::groups.show', ['group' => $group])
        ->set('composer', '<p>Halló hópur</p>')
        ->call('publish')
        ->assertSet('composer', '');

    expect(Post::query()->where('group_id', $group->id)->where('user_id', $user->id)->count())->toBe(1);
});

test('a non-member cannot post to the group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::groups.show', ['group' => $group])
        ->set('composer', '<p>Should not post</p>')
        ->call('publish');

    expect(Post::query()->where('group_id', $group->id)->count())->toBe(0);
});

test('a member can leave an interest group but not a department group', function () {
    $user = User::factory()->create();
    $interest = Group::factory()->create();
    $department = Group::factory()->department()->create();
    $interest->members()->attach($user->id, ['role' => GroupRole::Member->value]);
    $department->members()->attach($user->id, ['role' => GroupRole::Member->value]);

    Livewire::actingAs($user)
        ->test('pages::groups.show', ['group' => $interest])
        ->call('leave');
    expect($interest->hasMember($user))->toBeFalse();

    Livewire::actingAs($user)
        ->test('pages::groups.show', ['group' => $department])
        ->call('leave');
    expect($department->hasMember($user))->toBeTrue();
});
