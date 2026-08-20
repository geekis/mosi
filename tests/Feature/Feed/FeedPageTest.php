<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

test('the dashboard route renders the feed', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('the feed shows a post from a group the user belongs to', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([
        $user->id => ['role' => GroupRole::Member->value],
        $author->id => ['role' => GroupRole::Member->value],
    ]);
    $post = Post::factory()->inGroup($group)->create(['user_id' => $author->id]);

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->assertSee($post->author->display_name);
});

test('publishing a post creates a sanitised post and clears the composer', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->set('composer', '<p>Hæ <strong>öll</strong></p><script>alert(1)</script>')
        ->call('publish')
        ->assertSet('composer', '');

    $post = Post::query()->where('user_id', $user->id)->firstOrFail();

    expect($post->body)
        ->toContain('<strong>öll</strong>')
        ->not->toContain('script');
});

test('publishing is a no-op for empty content', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->set('composer', '   <p>  </p>  ')
        ->call('publish');

    expect(Post::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('switching to the latest tab surfaces a stranger’s public post', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create(['display_name' => 'Fjalar Þjóðólfsson']);
    Post::factory()->create(['user_id' => $stranger->id]);

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->set('tab', 'latest')
        ->assertSee('Fjalar Þjóðólfsson');
});

test('reacting to a visible post records the reaction', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->call('react', $post->id, 'like');

    expect($post->reactions()->where('user_id', $user->id)->where('type', 'like')->exists())->toBeTrue();
});

test('a user cannot react to a post they cannot see', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($author->id, ['role' => GroupRole::Member->value]);
    $post = Post::factory()->inGroup($secret)->create(['user_id' => $author->id]);

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->call('react', $post->id, 'like');

    expect($post->reactions()->count())->toBe(0);
});
