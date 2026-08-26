<?php

declare(strict_types=1);

use App\Actions\Posts\CreatePost;
use App\Enums\GroupRole;
use App\Events\GroupPostPublished;
use App\Models\Group;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function broadcastGroupWith(User ...$members): Group
{
    $group = Group::factory()->create(['name' => ['en' => 'Trail Crew', 'is' => 'Stígahópur']]);

    $group->members()->attach(
        collect($members)->mapWithKeys(fn (User $u) => [$u->id => ['role' => GroupRole::Member->value]])->all()
    );

    return $group;
}

test('publishing a group post broadcasts to the group channel', function () {
    Event::fake([GroupPostPublished::class]);
    $author = User::factory()->create();
    $group = broadcastGroupWith($author, User::factory()->create());

    app(CreatePost::class)($author, ['body' => 'Live!', 'group_id' => $group->id]);

    Event::assertDispatched(GroupPostPublished::class, fn (GroupPostPublished $e) => $e->groupId === $group->id);
});

test('a feed post broadcasts nothing to any group channel', function () {
    Event::fake([GroupPostPublished::class]);

    app(CreatePost::class)(User::factory()->create(), ['body' => 'Just a feed post']);

    Event::assertNotDispatched(GroupPostPublished::class);
});

test('a draft group post does not broadcast until it is live', function () {
    Event::fake([GroupPostPublished::class]);
    $author = User::factory()->create();
    $group = broadcastGroupWith($author);

    app(CreatePost::class)($author, ['body' => 'Later', 'group_id' => $group->id, 'published_at' => now()->addDay()]);

    Event::assertNotDispatched(GroupPostPublished::class);
});

test('the group channel authorises members only', function () {
    $member = User::factory()->create();
    $group = broadcastGroupWith($member);
    $outsider = User::factory()->create();

    // Invoke the registered channel callback directly — the test broadcaster is `null`,
    // which no-ops the /broadcasting/auth endpoint, so it can't be exercised over HTTP.
    $broadcaster = app(Broadcaster::class);
    $channels = (fn () => $this->channels)->call($broadcaster);
    $authorise = $channels['group.{groupId}'];

    expect($authorise($member, $group->id))->toBeTrue()
        ->and($authorise($outsider, $group->id))->toBeFalse();
});

test('the my-groups island lists the user groups and hides a non-member secret group', function () {
    $user = User::factory()->create();
    $mine = broadcastGroupWith($user);
    $secret = Group::factory()->secret()->create(['name' => ['en' => 'Inner Circle', 'is' => 'Innsti hringur']]);

    Livewire::actingAs($user)->test('pages::my-groups-nav')
        ->assertSee($mine->name)
        ->assertDontSee($secret->name);
});
