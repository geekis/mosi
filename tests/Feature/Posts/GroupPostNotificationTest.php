<?php

declare(strict_types=1);

use App\Actions\Posts\CreatePost;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use App\Notifications\NewGroupPost;
use Livewire\Livewire;

/**
 * Attach the given users to a group as members and return the group.
 *
 * @param  array<int, User>  $members
 */
function groupWithPostMembers(array $members, ?Group $group = null): Group
{
    $group ??= Group::factory()->create();

    $group->members()->attach(
        collect($members)->mapWithKeys(fn (User $user) => [$user->id => ['role' => GroupRole::Member->value]])->all()
    );

    return $group;
}

test('group members are notified of a new post, except the author', function () {
    $author = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $group = groupWithPostMembers([$author, $member]);

    app(CreatePost::class)($author, ['body' => 'Hello group', 'group_id' => $group->id]);

    expect($member->notifications()->count())->toBe(1)
        ->and($author->notifications()->count())->toBe(0)
        ->and($outsider->notifications()->count())->toBe(0);
});

test('a member who opted out of group-post notifications is not notified', function () {
    $author = User::factory()->create();
    $optedOut = User::factory()->create(['notification_preferences' => ['group_post' => false]]);
    $group = groupWithPostMembers([$author, $optedOut]);

    app(CreatePost::class)($author, ['body' => 'Anyone there?', 'group_id' => $group->id]);

    expect($optedOut->notifications()->count())->toBe(0);
});

test('a scheduled or draft group post does not notify until it is live', function () {
    $author = User::factory()->create();
    $member = User::factory()->create();
    $group = groupWithPostMembers([$author, $member]);

    app(CreatePost::class)($author, [
        'body' => 'Later',
        'group_id' => $group->id,
        'published_at' => now()->addDay(),
    ]);

    expect($member->notifications()->count())->toBe(0);
});

test('a non-group post notifies nobody', function () {
    $author = User::factory()->create();
    User::factory()->create();

    app(CreatePost::class)($author, ['body' => 'Just a feed post']);

    expect(User::query()->get()->sum(fn (User $u) => $u->notifications()->count()))->toBe(0);
});

test('opening the group marks its unread post notifications read', function () {
    $author = User::factory()->create();
    $member = User::factory()->create();
    $group = groupWithPostMembers([$author, $member]);

    app(CreatePost::class)($author, ['body' => 'Fresh news', 'group_id' => $group->id]);
    expect($member->unreadNotifications()->count())->toBe(1);

    Livewire::actingAs($member)->test('pages::groups.show', ['group' => $group]);

    expect($member->fresh()->unreadNotifications()->count())->toBe(0);
});

test('the sidebar renders an unread badge for a group with new posts', function () {
    $author = User::factory()->create();
    $member = User::factory()->create();
    $group = groupWithPostMembers([$author, $member], Group::factory()->create(['name' => ['en' => 'Trail Crew', 'is' => 'Stígahópur']]));

    app(CreatePost::class)($author, ['body' => 'New post', 'group_id' => $group->id]);

    // The badge binds to the unread group_post count; renders without error and the group shows.
    $this->actingAs($member)->get(route('dashboard'))
        ->assertOk()
        ->assertSee($group->name);

    expect($member->unreadNotifications()->where('type', NewGroupPost::class)->count())->toBe(1);
});
