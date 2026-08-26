<?php

declare(strict_types=1);

use App\Actions\Reactions\React;
use App\Enums\GroupRole;
use App\Enums\ReactionType;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the feed composer routes an image, a video and a file into the right collections', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->set('composer', '<p>Á ferðinni</p>')
        ->set('attachments', [
            UploadedFile::fake()->image('mynd.jpg'),
            UploadedFile::fake()->create('klipp.mp4', 400, 'video/mp4'),
            UploadedFile::fake()->create('skyrsla.pdf', 120, 'application/pdf'),
        ])
        ->call('publish')
        ->assertHasNoErrors()
        ->assertSet('composer', '')
        ->assertSet('attachments', []);

    $post = Post::query()->where('user_id', $user->id)->firstOrFail();

    expect($post->getMedia('images'))->toHaveCount(1)
        ->and($post->getMedia('videos'))->toHaveCount(1)
        ->and($post->getMedia('attachments'))->toHaveCount(1);
});

test('a post may be image-only with an empty body', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->set('attachments', [UploadedFile::fake()->image('sunset.png')])
        ->call('publish');

    $post = Post::query()->where('user_id', $user->id)->firstOrFail();

    expect($post->getMedia('images'))->toHaveCount(1)
        ->and(trim(strip_tags($post->body)))->toBe('');
});

test('publishing is still a no-op with neither text nor attachments', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->set('composer', '   <p>  </p>  ')
        ->call('publish');

    expect(Post::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('the group composer attaches media for a member', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach($user->id, ['role' => GroupRole::Member->value]);

    Livewire::actingAs($user)
        ->test('pages::groups.show', ['group' => $group])
        ->set('composer', '<p>Fundur á morgun</p>')
        ->set('attachments', [UploadedFile::fake()->image('dagskra.jpg')])
        ->call('publish')
        ->assertHasNoErrors()
        ->assertSet('attachments', []);

    $post = Post::query()->where('group_id', $group->id)->firstOrFail();

    expect($post->getMedia('images'))->toHaveCount(1)
        ->and($post->user_id)->toBe($user->id);
});

test('a non-member cannot publish into a group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::groups.show', ['group' => $group])
        ->set('composer', '<p>Óboðinn gestur</p>')
        ->call('publish');

    expect(Post::query()->where('group_id', $group->id)->count())->toBe(0);
});

test('reacting with the same type twice toggles the reaction off', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->call('react', $post->id, 'love')
        ->call('react', $post->id, 'love');

    expect($post->reactions()->count())->toBe(0);
});

test('reacting with a different type replaces the previous reaction', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::feed')
        ->call('react', $post->id, 'love')
        ->call('react', $post->id, 'wow');

    expect($post->reactions()->count())->toBe(1)
        ->and($post->reactions()->first()->type->value)->toBe('wow');
});

test('showReactions lists who reacted and with which emoji', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);
    $fan = User::factory()->create(['display_name' => 'Katla Gunnarsdóttir']);
    app(React::class)($fan, $post, ReactionType::Love);

    $component = Livewire::actingAs($author)
        ->test('pages::feed')
        ->call('showReactions', $post->id);

    $list = $component->get('postReactionList');

    expect($list)->toHaveCount(1)
        ->and($list[0]['name'])->toBe('Katla Gunnarsdóttir')
        ->and($list[0]['emoji'])->toBe(ReactionType::Love->emoji())
        ->and($list[0]['label'])->toBe(ReactionType::Love->label());
});

test('a viewer can comment on a post from the feed', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);
    $commenter = User::factory()->create();

    Livewire::actingAs($commenter)
        ->test('pages::feed')
        ->set("commentDrafts.{$post->id}", 'Vel gert!')
        ->call('addComment', $post->id)
        ->assertSet("commentDrafts.{$post->id}", null);

    $comment = $post->comments()->firstOrFail();

    expect($comment->body)->toBe('Vel gert!')
        ->and($comment->user_id)->toBe($commenter->id);
});

test('commenting is a no-op when the draft is blank', function () {
    $post = Post::factory()->create();
    $commenter = User::factory()->create();

    Livewire::actingAs($commenter)
        ->test('pages::feed')
        ->set("commentDrafts.{$post->id}", '   ')
        ->call('addComment', $post->id);

    expect($post->comments()->count())->toBe(0);
});

test('a user cannot comment on a post they cannot see', function () {
    $author = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($author->id, ['role' => GroupRole::Member->value]);
    $post = Post::factory()->inGroup($secret)->create(['user_id' => $author->id]);
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::feed')
        ->set("commentDrafts.{$post->id}", 'Leyfðu mér inn')
        ->call('addComment', $post->id);

    expect($post->comments()->count())->toBe(0);
});
