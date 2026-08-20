<?php

declare(strict_types=1);

use App\Actions\Comments\AddComment;
use App\Actions\Follows\FollowUser;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentOnYourPost;
use App\Notifications\DailyDigest;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('following a user notifies them once', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    app(FollowUser::class)($follower, $followed);
    app(FollowUser::class)($follower, $followed); // re-follow is silent

    expect($followed->notifications()->count())->toBe(1)
        ->and($followed->notifications()->first()->data['key'])->toBe('new_follower');
});

test('commenting on a post notifies the author but not the commenter', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    app(AddComment::class)($commenter, $post, 'Nice post');

    expect($author->notifications()->where('type', CommentOnYourPost::class)->count())->toBe(1)
        ->and($commenter->notifications()->count())->toBe(0);
});

test('commenting on your own post notifies no one', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id]);

    app(AddComment::class)($author, $post, 'Replying to myself');

    expect($author->notifications()->count())->toBe(0);
});

test('a disabled preference suppresses the notification', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create(['notification_preferences' => ['new_follower' => false]]);

    app(FollowUser::class)($follower, $followed);

    // via() returns no channels, so nothing is stored.
    expect($followed->notifications()->count())->toBe(0);
});

test('the bell shows the unread count and marks all read', function () {
    $user = User::factory()->create();
    $follower = User::factory()->create();
    app(FollowUser::class)($follower, $user);

    $component = Livewire::actingAs($user)->test('pages::notification-bell');
    $component->assertViewHas('unreadCount', 1);

    $component->call('markAllRead');
    expect($user->unreadNotifications()->count())->toBe(0);
});

test('the digest renders in the recipient locale', function () {
    $user = User::factory()->create(['locale' => 'is']);

    $mail = (new DailyDigest(3))->toMail($user);

    expect($mail->subject)->toBe(__('notifications.digest.subject', [], 'is'));
});

test('the digest command emails only users with recent unread notifications', function () {
    $active = User::factory()->create();
    $follower = User::factory()->create();
    app(FollowUser::class)($follower, $active); // persists a real DB notification

    User::factory()->create(); // no notifications → no digest

    Notification::fake();
    $this->artisan('app:send-digest')->assertSuccessful();

    Notification::assertSentTo($active, DailyDigest::class);
});
