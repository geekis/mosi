<?php

use App\Actions\Feed\BuildFeed;
use App\Actions\Feed\BuildLatest;
use App\Actions\Posts\CreatePost;
use App\Actions\Reactions\React;
use App\Enums\ReactionType;
use App\Models\Event;
use App\Models\Post;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mosi')] class extends Component {
    public string $tab = 'for-you';

    public string $composer = '';

    /**
     * Publish the composed post. The action sanitises the HTML and notifies mentions.
     */
    public function publish(CreatePost $createPost): void
    {
        if (trim(strip_tags($this->composer)) === '') {
            return;
        }

        $createPost(Auth::user(), ['body' => $this->composer]);

        $this->composer = '';
        Flux::modal('composer')->close();
        Flux::toast(text: __('Your post is published.'), variant: 'success');
    }

    public function react(int $postId, string $type, React $react): void
    {
        $post = Post::find($postId);
        $reaction = ReactionType::tryFrom($type);

        if ($post === null || $reaction === null || ! Auth::user()->can('view', $post)) {
            return;
        }

        $react(Auth::user(), $post, $reaction);
    }

    /**
     * @return array{me: \App\Models\User, posts: \Illuminate\Contracts\Pagination\CursorPaginator, myGroups: \Illuminate\Support\Collection<int, \App\Models\Group>}
     */
    public function with(BuildFeed $buildFeed, BuildLatest $buildLatest): array
    {
        $user = Auth::user();

        return [
            'me' => $user,
            'posts' => $this->tab === 'latest'
                ? $buildLatest($user, 15)
                : $buildFeed($user, 15),
            'myGroups' => $user->groups()->orderBy('id')->get(),
            'followersCount' => $user->followers()->count(),
            'followingCount' => $user->following()->count(),
            'postsCount' => Post::where('user_id', $user->id)->count(),
            'upcomingEvents' => Event::query()->visibleTo($user)->upcoming()->limit(3)->get(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl gap-6">
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
            <flux:heading size="sm">{{ __('My groups') }}</flux:heading>
            @forelse ($myGroups as $group)
                <div wire:key="rail-group-{{ $group->id }}" class="flex items-center gap-2 text-sm">
                    <flux:icon icon="user-group" variant="mini" class="text-zinc-400" />
                    <span class="truncate">{{ $group->name }}</span>
                </div>
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
    {{-- Composer trigger: opens the post modal. --}}
    <flux:card>
        <div class="flex items-center gap-3">
            <flux:avatar :name="$me->display_name" :initials="$me->initials()" :src="$me->avatarUrl()" size="sm" />

            <flux:modal.trigger name="composer" class="flex-1">
                <button type="button" class="w-full rounded-full bg-zinc-100 px-4 py-2 text-start text-sm text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600">
                    {{ __("What's going on, :name?", ['name' => $me->given_name ?? $me->display_name]) }}
                </button>
            </flux:modal.trigger>
        </div>

        <flux:separator class="my-3" variant="subtle" />

        <div class="flex flex-wrap items-center justify-between gap-1">
            <flux:modal.trigger name="composer">
                <flux:button size="sm" variant="ghost" icon="hand-thumb-up">{{ __('Give a Shout-Out') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="composer">
                <flux:button size="sm" variant="ghost" icon="sparkles">{{ __('Post a Value Update') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="composer">
                <flux:button size="sm" variant="ghost" icon="question-mark-circle">{{ __('Ask a Question') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </flux:card>

    {{-- Composer modal --}}
    <flux:modal name="composer" class="w-full max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Create a post') }}</flux:heading>
            <flux:editor wire:model="composer" :placeholder="__('Share something with your colleagues…')" />
            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="publish" data-test="publish-button">
                    {{ __('Post') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Tabs --}}
    <flux:tabs wire:model.live="tab" class="mt-6">
        <flux:tab name="for-you">{{ __('For you') }}</flux:tab>
        <flux:tab name="latest">{{ __('Latest') }}</flux:tab>
    </flux:tabs>

    {{-- Feed --}}
    <div class="mt-4 space-y-4">
        @forelse ($posts as $post)
            <flux:card wire:key="post-{{ $post->id }}" @class(['ring-1 ring-primary-500/40' => $post->is_official])>
                <div class="flex items-center gap-3">
                    <flux:avatar :name="$post->author?->display_name" :src="$post->author?->avatarUrl()" size="sm" />

                    <div class="min-w-0">
                        @if ($post->author?->external_id)
                            <a href="{{ route('people.show', $post->author) }}" class="hover:underline" wire:navigate>
                                <flux:heading class="truncate">{{ $post->author->display_name }}</flux:heading>
                            </a>
                        @else
                            <flux:heading class="truncate">{{ $post->author?->display_name }}</flux:heading>
                        @endif

                        <flux:text size="sm" class="flex items-center gap-1 truncate">
                            @if ($post->group)
                                <span class="truncate">{{ $post->group->name }}</span>
                                <span>·</span>
                            @endif
                            <span>{{ $post->published_at?->diffForHumans() }}</span>
                            @if ($post->is_official)
                                <flux:badge size="sm" color="green">{{ __('Official') }}</flux:badge>
                            @endif
                        </flux:text>
                    </div>
                </div>

                <div class="prose prose-sm mt-3 max-w-none whitespace-pre-line dark:prose-invert">
                    {!! $post->body !!}
                </div>

                <flux:separator class="my-3" variant="subtle" />

                <div class="flex items-center gap-2 text-sm">
                    <flux:button size="xs" variant="ghost" icon="hand-thumb-up" wire:click="react({{ $post->id }}, 'like')">
                        {{ $post->reactions_count }}
                    </flux:button>

                    <flux:button size="xs" variant="ghost" icon="chat-bubble-oval-left-ellipsis">
                        {{ $post->comments_count }}
                    </flux:button>
                </div>
            </flux:card>
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
</div>
