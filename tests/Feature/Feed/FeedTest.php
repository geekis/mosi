<?php

declare(strict_types=1);

use App\Actions\Feed\BuildFeed;
use App\Actions\Feed\BuildLatest;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @return array<int, int>
 */
function feedIds(User $user, int $perPage = 50): array
{
    return app(BuildFeed::class)($user, $perPage)->pluck('id')->all();
}

it('includes posts from groups the user belongs to', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach([$user->id => ['role' => GroupRole::Member->value], $author->id => ['role' => GroupRole::Member->value]]);

    $post = Post::factory()->inGroup($group)->create(['user_id' => $author->id]);

    expect(feedIds($user))->toContain($post->id);
});

it('includes posts from people the user follows', function () {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed->id);

    $post = Post::factory()->create(['user_id' => $followed->id]);

    expect(feedIds($user))->toContain($post->id);
});

it('includes the user’s own posts', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    expect(feedIds($user))->toContain($post->id);
});

it('excludes posts from groups the user is not in and does not follow', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $group = Group::factory()->create();
    $group->members()->attach($author->id, ['role' => GroupRole::Member->value]);

    $post = Post::factory()->inGroup($group)->create(['user_id' => $author->id]);

    expect(feedIds($user))->not->toContain($post->id);
});

it('never surfaces a secret-group post from a followed author the user cannot see', function () {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed->id);

    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($followed->id, ['role' => GroupRole::Member->value]);
    $post = Post::factory()->inGroup($secret)->create(['user_id' => $followed->id]);

    expect(feedIds($user))->not->toContain($post->id);
});

it('excludes drafts and scheduled posts', function () {
    $user = User::factory()->create();
    $draft = Post::factory()->draft()->create(['user_id' => $user->id]);
    $scheduled = Post::factory()->scheduled()->create(['user_id' => $user->id]);
    $published = Post::factory()->create(['user_id' => $user->id]);

    $ids = feedIds($user);

    expect($ids)->toContain($published->id)
        ->and($ids)->not->toContain($draft->id)
        ->and($ids)->not->toContain($scheduled->id);
});

it('boosts an official post above an older regular post', function () {
    $user = User::factory()->create();
    $regular = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()->subDays(2)]);
    $official = Post::factory()->official()->create(['user_id' => $user->id, 'published_at' => now()->subDays(5)]);

    $ids = feedIds($user);

    expect(array_search($official->id, $ids, true))->toBeLessThan(array_search($regular->id, $ids, true));
});

it('cursor-paginates the feed', function () {
    $user = User::factory()->create();
    Post::factory()->count(5)->create(['user_id' => $user->id]);

    $page = app(BuildFeed::class)($user, 2);

    expect($page->count())->toBe(2)
        ->and($page->hasMorePages())->toBeTrue();
});

/**
 * @return array<int, int>
 */
function latestIds(User $user, int $perPage = 50): array
{
    return app(BuildLatest::class)($user, $perPage)->pluck('id')->all();
}

it('latest surfaces published posts the user can see, regardless of follow or membership', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $own = Post::factory()->create(['user_id' => $user->id]);
    $strangerPost = Post::factory()->create(['user_id' => $stranger->id]);

    $ids = latestIds($user);

    expect($ids)->toContain($own->id)->and($ids)->toContain($strangerPost->id);
});

it('latest never leaks a secret-group post to a non-member', function () {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $secret = Group::factory()->secret()->create();
    $secret->members()->attach($author->id, ['role' => GroupRole::Member->value]);
    $post = Post::factory()->inGroup($secret)->create(['user_id' => $author->id]);

    expect(latestIds($user))->not->toContain($post->id);
});

it('latest excludes drafts and scheduled posts', function () {
    $user = User::factory()->create();
    $draft = Post::factory()->draft()->create(['user_id' => $user->id]);
    $scheduled = Post::factory()->scheduled()->create(['user_id' => $user->id]);
    $published = Post::factory()->create(['user_id' => $user->id]);

    $ids = latestIds($user);

    expect($ids)->toContain($published->id)
        ->and($ids)->not->toContain($draft->id)
        ->and($ids)->not->toContain($scheduled->id);
});

it('renders a feed page in a constant number of queries', function () {
    $user = User::factory()->create();
    Post::factory()->count(12)->create(['user_id' => $user->id]);

    DB::enableQueryLog();
    $feed = app(BuildFeed::class)($user, 12);
    $feed->each(function (Post $post): void {
        $post->author?->display_name;
        $post->reactions_count;
        $post->comments_count;
        $post->getMedia('images');
    });
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Feed query + groups + follows + eager loads — bounded, never N+1 across 12 posts.
    expect($queries)->toBeLessThanOrEqual(10);
});
