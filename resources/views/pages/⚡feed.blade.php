<?php

use App\Actions\Feed\BuildFeed;
use App\Actions\Feed\BuildLatest;
use App\Actions\Posts\CreatePost;
use App\Concerns\InteractsWithPostComposer;
use App\Models\Event;
use App\Models\Post;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mosi')] class extends Component {
    use InteractsWithPostComposer;

    public string $tab = 'for-you';

    /**
     * Publish the composed post with any attachments. The action sanitises the HTML and
     * notifies mentions; media is routed into its collections afterwards.
     */
    public function publish(CreatePost $createPost): void
    {
        if (trim(strip_tags($this->composer)) === '' && $this->attachments === []) {
            return;
        }

        $this->validateAttachments();

        $post = $createPost(Auth::user(), ['body' => $this->composer]);
        $this->attachMediaTo($post);

        $this->resetComposer();
        Flux::modal('composer')->close();
        Flux::toast(text: __('Your post is published.'), variant: 'success');
    }

    /**
     * @return array{me: \App\Models\User, posts: \Illuminate\Contracts\Pagination\CursorPaginator, myGroups: \Illuminate\Support\Collection<int, \App\Models\Group>}
     */
    public function with(BuildFeed $buildFeed, BuildLatest $buildLatest): array
    {
        $user = Auth::user();
        $mentions = $this->mentionData();

        return [
            'me' => $user,
            'mentionMap' => $mentions['map'],
            'posts' => $this->tab === 'latest'
                ? $buildLatest($user, 15)
                : $buildFeed($user, 15),
            'myGroups' => $user->groups()->orderBy('id')->get(),
            'mentionables' => $mentions['list'],
            'followersCount' => $user->followers()->count(),
            'followingCount' => $user->following()->count(),
            'postsCount' => Post::where('user_id', $user->id)->count(),
            'upcomingEvents' => Event::query()->visibleTo($user)->upcoming()->limit(3)->get(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl gap-6"
    x-data="{
        viewer: null,
        open(kind, url, name) { this.viewer = { kind, url, name }; },
        close() { this.viewer = null; },
    }"
    x-on:keydown.escape.window="close()">
    {{-- Left rail: who you are, your groups, quick links. Appears at lg. --}}
    <aside class="hidden w-72 shrink-0 space-y-4 lg:block">
        <flux:card class="overflow-hidden p-0">
            {{-- Banner --}}
            <div class="h-20 w-full bg-gradient-to-br from-primary-600 to-primary-800">
                @if ($me->bannerUrl())
                    <img src="{{ $me->bannerUrl() }}" alt="" class="h-20 w-full object-cover" />
                @endif
            </div>

            <div class="px-4 pb-4 text-center">
                {{-- Avatar overlapping the banner --}}
                <div class="-mt-10 flex justify-center">
                    <flux:avatar :name="$me->display_name" :initials="$me->initials()" :src="$me->avatarUrl()" size="xl" class="ring-4 ring-white dark:ring-zinc-800" />
                </div>

                <div class="mt-2">
                    @if ($me->external_id)
                        <a href="{{ route('people.show', $me) }}" wire:navigate class="hover:underline">
                            <flux:heading size="lg">{{ $me->display_name }}</flux:heading>
                        </a>
                    @else
                        <flux:heading size="lg">{{ $me->display_name }}</flux:heading>
                    @endif
                    @if ($me->job_title)
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $me->job_title }}</flux:text>
                    @endif
                </div>

                {{-- Stats --}}
                <div class="mt-4 grid grid-cols-3 divide-x divide-zinc-200 dark:divide-zinc-700">
                    <div class="px-1">
                        <flux:heading size="lg">{{ $followersCount }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Followers') }}</flux:text>
                    </div>
                    <div class="px-1">
                        <flux:heading size="lg">{{ $followingCount }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Following') }}</flux:text>
                    </div>
                    <div class="px-1">
                        <flux:heading size="lg">{{ $postsCount }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Posts') }}</flux:text>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('My groups') }}</flux:heading>
                <a href="{{ route('groups') }}" wire:navigate class="text-sm text-primary-600 hover:underline">{{ __('Browse') }}</a>
            </div>
            @forelse ($myGroups as $group)
                <a wire:key="rail-group-{{ $group->id }}" href="{{ route('groups.show', $group) }}" wire:navigate
                    class="flex items-center gap-2 text-sm hover:underline">
                    <flux:icon icon="user-group" variant="mini" class="text-zinc-400" />
                    <span class="truncate">{{ $group->name }}</span>
                </a>
            @empty
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('You are not in any groups yet.') }}
                </flux:text>
            @endforelse
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="mb-2">{{ __('Quick links') }}</flux:heading>
            <flux:navlist>
                <flux:navlist.item icon="home" :href="route('dashboard')" wire:navigate>{{ __('Home') }}</flux:navlist.item>
                <flux:navlist.item icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate>{{ __('Settings') }}</flux:navlist.item>
            </flux:navlist>
        </flux:card>
    </aside>

    {{-- Center column: composer, tabs and the feed --}}
    <div class="min-w-0 flex-1">
    <x-post-composer :me="$me" :placeholder="__('Share something with your colleagues…')"
        :attachments="$attachments" :mentionables="$mentionables" post-test="publish-button" />

    {{-- Tabs --}}
    <flux:tabs wire:model.live="tab" variant="segmented" class="mt-6">
        <flux:tab name="for-you">{{ __('For you') }}</flux:tab>
        <flux:tab name="latest">{{ __('Latest') }}</flux:tab>
    </flux:tabs>

    {{-- Feed --}}
    <div class="mt-4 space-y-4">
        @forelse ($posts as $post)
            <x-post-card :post="$post" :mentionables="$mentionables" :mention-map="$mentionMap" wire:key="post-{{ $post->id }}" />
        @empty
            <flux:callout icon="rss">
                {{ __('Your feed is quiet. Join a group or follow colleagues to see their posts here.') }}
            </flux:callout>
        @endforelse
    </div>
    </div>

    {{-- Right rail: widgets arrive with later phases (directory, events). Placeholders for now. --}}
    <aside class="hidden w-80 shrink-0 space-y-4 xl:block">
        <x-rail.placeholder :heading="__('Birthdays today')" icon="cake" />

        <flux:card class="space-y-3">
            <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                <flux:icon icon="calendar" variant="mini" />
                <flux:heading size="sm">{{ __('Upcoming events') }}</flux:heading>
            </div>
            @forelse ($upcomingEvents as $event)
                <a wire:key="rail-event-{{ $event->id }}" href="{{ route('events.show', $event) }}" wire:navigate class="block hover:opacity-80">
                    <flux:text class="truncate font-medium">{{ $event->title }}</flux:text>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $event->starts_at->translatedFormat('j. M, H:i') }}</flux:text>
                </a>
            @empty
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No upcoming events.') }}</flux:text>
            @endforelse
        </flux:card>

        <x-rail.placeholder :heading="__('New colleagues')" icon="user-plus" />
    </aside>

    <x-post-media-viewer />
    <x-post-reactions-modal :reactions="$postReactionList" />
</div>
