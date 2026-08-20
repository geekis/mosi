<?php

declare(strict_types=1);

use App\Actions\Search\SearchEverything;
use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

function search(User $user, string $term): array
{
    return app(SearchEverything::class)($user, $term);
}

test('search finds people, groups, posts and events by term', function () {
    $viewer = User::factory()->create();
    $person = User::factory()->create(['display_name' => 'Sigríður Fossberg', 'job_title' => 'Jarðfræðingur']);
    $group = Group::factory()->create(['name' => ['is' => 'Fossberg hópur', 'en' => 'Fossberg group']]);
    $post = Post::factory()->create(['user_id' => $viewer->id, 'body' => 'Heimsókn í Fossberg']);
    $event = Event::factory()->create(['title' => ['is' => 'Fossberg ganga', 'en' => 'Fossberg walk'], 'starts_at' => now()->addDay()]);

    $results = search($viewer, 'Fossberg');

    expect($results['people']->pluck('id'))->toContain($person->id)
        ->and($results['groups']->pluck('id'))->toContain($group->id)
        ->and($results['posts']->pluck('id'))->toContain($post->id)
        ->and($results['events']->pluck('id'))->toContain($event->id);
});

test('an empty term returns nothing', function () {
    $viewer = User::factory()->create();
    Post::factory()->create(['user_id' => $viewer->id, 'body' => 'anything']);

    $results = search($viewer, '   ');

    expect($results['posts'])->toBeEmpty()->and($results['people'])->toBeEmpty();
});

test('a secret group never appears in search for a non-member', function () {
    $viewer = User::factory()->create();
    $secret = Group::factory()->secret()->create(['name' => ['is' => 'Leyniverkefni', 'en' => 'Secret project']]);

    expect(search($viewer, 'Leyniverkefni')['groups'])->toBeEmpty();
});

test('a post in a secret group never appears in search for a non-member', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($author->id, ['role' => GroupRole::Member->value]);
    Post::factory()->inGroup($secret)->create(['user_id' => $author->id, 'body' => 'Trúnaðarmál um Leyniverkefni']);

    expect(search($viewer, 'Trúnaðarmál')['posts'])->toBeEmpty();
});

test('a member does see their secret group and its posts in search', function () {
    $member = User::factory()->create();
    $secret = Group::factory()->secret()->create(['name' => ['is' => 'Leyniverkefni', 'en' => 'Secret project']]);
    $secret->members()->attach($member->id, ['role' => GroupRole::Member->value]);
    Post::factory()->inGroup($secret)->create(['user_id' => $member->id, 'body' => 'Fundur um Leyniverkefni']);

    $results = search($member, 'Leyniverkefni');

    expect($results['groups'])->toHaveCount(1)
        ->and($results['posts'])->toHaveCount(1);
});

test('the search page renders matches for the query', function () {
    $viewer = User::factory()->create();
    User::factory()->create(['display_name' => 'Þórunn Leitardóttir']);

    Livewire::actingAs($viewer)
        ->test('pages::search', ['q' => 'Leitardóttir'])
        ->assertOk()
        ->assertSee('Þórunn Leitardóttir');
});
