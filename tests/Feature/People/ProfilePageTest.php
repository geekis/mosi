<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

test('a profile page renders for an active user', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create(['job_title' => 'Þjóðgarðsvörður']);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['user' => $profile])
        ->assertOk()
        ->assertSee($profile->display_name)
        ->assertSee('Þjóðgarðsvörður');
});

test('the profile route resolves and links by external_id, not id', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create(['external_id' => '424242']);

    expect(route('people.show', $profile))->toContain('/people/424242');

    $this->actingAs($viewer)
        ->get(route('people.show', $profile))
        ->assertOk()
        ->assertSee($profile->display_name);
});

test('an inactive user profile is hidden from colleagues', function () {
    $viewer = User::factory()->create();
    $inactive = User::factory()->inactive()->create();

    $this->actingAs($viewer)
        ->get(route('people.show', $inactive))
        ->assertNotFound();
});

test('a secret group the viewer cannot see is not listed on a profile', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $secret = Group::factory()->secret()->create(['name' => ['en' => 'Covert Ops', 'is' => 'Leyniverkefni']]);
    $secret->members()->attach($profile->id, ['role' => GroupRole::Member->value]);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['user' => $profile])
        ->assertDontSee($secret->name);
});

test('a shared secret group is listed when the viewer is also a member', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $secret = Group::factory()->secret()->create(['name' => ['en' => 'Inner Circle', 'is' => 'Innsti hringur']]);
    $secret->members()->attach([
        $viewer->id => ['role' => GroupRole::Member->value],
        $profile->id => ['role' => GroupRole::Member->value],
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['user' => $profile])
        ->assertSee($secret->name);
});

test('a profile only shows posts the viewer is allowed to see', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($profile->id, ['role' => GroupRole::Member->value]);

    Post::factory()->inGroup($secret)->create(['user_id' => $profile->id, 'body' => 'SECRET-POST-BODY']);
    Post::factory()->create(['user_id' => $profile->id, 'body' => 'PUBLIC-POST-BODY']);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['user' => $profile])
        ->assertSee('PUBLIC-POST-BODY')
        ->assertDontSee('SECRET-POST-BODY');
});

test('a user can follow and unfollow from a profile', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();

    $component = Livewire::actingAs($viewer)->test('pages::people.show', ['user' => $profile]);

    $component->call('follow');
    expect($viewer->fresh()->isFollowing($profile))->toBeTrue();

    $component->call('unfollow');
    expect($viewer->fresh()->isFollowing($profile))->toBeFalse();
});

test('a profile shows post and follower counts that update when you follow', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    Post::factory()->count(2)->create(['user_id' => $profile->id]);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['user' => $profile])
        ->assertViewHas('postsCount', 2)
        ->assertViewHas('followersCount', 0)
        ->call('follow')
        ->assertViewHas('followersCount', 1);
});

test('the "follows you" badge shows only when the person follows the viewer', function () {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['user' => $profile])
        ->assertDontSee('Fylgir þér');

    $profile->following()->attach($viewer->id);

    Livewire::actingAs($viewer)
        ->test('pages::people.show', ['user' => $profile])
        ->assertSee('Fylgir þér');
});

test('the follow action is a no-op on your own profile', function () {
    $me = User::factory()->create();

    Livewire::actingAs($me)
        ->test('pages::people.show', ['user' => $me])
        ->call('follow');

    expect($me->fresh()->following()->count())->toBe(0);
});
