<?php

use App\Actions\Chat\StartDirectConversation;
use App\Actions\Follows\FollowUser;
use App\Actions\Follows\UnfollowUser;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mosi')] class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->can('view', $user), 404);

        $this->user = $user;
    }

    public function follow(FollowUser $follow): void
    {
        if (! Auth::user()->is($this->user)) {
            $follow(Auth::user(), $this->user);
        }
    }

    public function unfollow(UnfollowUser $unfollow): void
    {
        $unfollow(Auth::user(), $this->user);
    }

    public function message(StartDirectConversation $startDirectConversation): void
    {
        if (Auth::user()->is($this->user)) {
            return;
        }

        $conversation = $startDirectConversation(Auth::user(), $this->user);

        $this->redirect(route('chat', ['conversation' => $conversation->id]), navigate: true);
    }

    /**
     * @return array{isSelf: bool, isFollowing: bool, followsYou: bool, postsCount: int, followersCount: int, followingCount: int, whereaboutsToday: ?\App\Models\Whereabouts, groups: \Illuminate\Support\Collection<int, \App\Models\Group>, posts: \Illuminate\Support\Collection<int, Post>}
     */
    public function with(): array
    {
        $viewer = Auth::user();

        $visiblePosts = Post::query()
            ->visibleTo($viewer)
            ->where('user_id', $this->user->id)
            ->published();

        return [
            'isSelf' => $viewer->is($this->user),
            'isFollowing' => $viewer->isFollowing($this->user),
            // A small social cue: does this person follow the viewer back?
            'followsYou' => ! $viewer->is($this->user) && $this->user->isFollowing($viewer),
            'postsCount' => (clone $visiblePosts)->count(),
            'followersCount' => $this->user->followers()->count(),
            'followingCount' => $this->user->following()->count(),
            // Today's whereabouts; the real absence reason is only revealed on your own profile.
            'whereaboutsToday' => $this->user->whereaboutsOn(now()),
            // Secret groups the viewer cannot see never appear on someone else's profile.
            'groups' => $this->user->groups()->visibleTo($viewer)->orderBy('id')->get(),
            'posts' => (clone $visiblePosts)
                ->with(['group', 'media'])
                ->withCount(['reactions', 'comments'])
                ->latest('published_at')
                ->limit(10)
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    {{-- Banner + avatar --}}
    <x-ui.card class="overflow-hidden p-0">
        <div class="h-40 w-full bg-gradient-to-br from-brand-600 to-brand-800">
            @if ($user->bannerUrl())
                <img src="{{ $user->bannerUrl() }}" alt="" class="h-40 w-full object-cover" />
            @endif
        </div>

        <div class="px-6 pb-6">
            <div class="-mt-12 flex items-end justify-between">
                <x-avatar :user="$user" size="xl" class="ring-4 ring-white dark:ring-zinc-800 rounded-full" />

                <div class="mb-1 flex items-center gap-2">
                    @if ($isSelf)
                        <x-ui.button :href="route('profile.edit')" size="sm" variant="ghost" icon="pencil-square" wire:navigate>
                            {{ __('Edit profile') }}
                        </x-ui.button>
                    @else
                        <x-ui.button size="sm" variant="ghost" icon="chat-bubble-left-right" wire:click="message">
                            {{ __('Message') }}
                        </x-ui.button>
                    @endif
                    @if ($isSelf)
                        {{-- no follow button on your own profile --}}
                    @elseif ($isFollowing)
                        <x-ui.button size="sm" variant="ghost" icon="user-minus" wire:click="unfollow">
                            {{ __('Following') }}
                        </x-ui.button>
                    @else
                        <x-ui.button size="sm" variant="primary" icon="user-plus" wire:click="follow">
                            {{ __('Follow') }}
                        </x-ui.button>
                    @endif
                </div>
            </div>

            <div class="mt-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.heading size="xl">{{ $user->display_name }}</x-ui.heading>
                    @if ($followsYou)
                        <x-ui.badge size="sm" color="zinc">{{ __('Follows you') }}</x-ui.badge>
                    @endif
                </div>
                <x-user-presence :user="$user" class="mt-1" />
                @if ($user->job_title)
                    <x-ui.text class="mt-1 font-medium text-ink-800 dark:text-zinc-200">{{ $user->job_title }}</x-ui.text>
                @endif
                @if ($user->organization_name)
                    <x-ui.text size="sm">{{ $user->organization_name }}</x-ui.text>
                @endif
            </div>

            {{-- Social stats --}}
            <div class="mt-4 flex items-center gap-6">
                <div class="flex items-baseline gap-1.5">
                    <span class="font-display text-lg font-semibold text-ink-900 dark:text-white">{{ $postsCount }}</span>
                    <span class="text-sm text-mist-500 dark:text-zinc-400">{{ __('Posts') }}</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="font-display text-lg font-semibold text-ink-900 dark:text-white">{{ $followersCount }}</span>
                    <span class="text-sm text-mist-500 dark:text-zinc-400">{{ __('Followers') }}</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="font-display text-lg font-semibold text-ink-900 dark:text-white">{{ $followingCount }}</span>
                    <span class="text-sm text-mist-500 dark:text-zinc-400">{{ __('Following') }}</span>
                </div>
            </div>

            @if ($user->bio)
                <x-ui.text class="mt-4 whitespace-pre-line">{{ $user->bio }}</x-ui.text>
            @endif
        </div>
    </x-ui.card>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        {{-- Contact & org details --}}
        <x-ui.card class="space-y-3 md:col-span-1">
            <x-ui.heading size="sm">{{ __('Details') }}</x-ui.heading>

            @if ($user->email)
                <div class="flex items-center gap-2 text-sm">
                    <x-ui.icon name="envelope" variant="mini" class="text-mist-400" />
                    <a href="mailto:{{ $user->email }}" class="truncate hover:underline">{{ $user->email }}</a>
                </div>
            @endif
            @if ($user->phone)
                <div class="flex items-center gap-2 text-sm">
                    <x-ui.icon name="phone" variant="mini" class="text-mist-400" />
                    <a href="tel:{{ $user->phone }}" class="truncate hover:underline">{{ $user->phone }}</a>
                </div>
            @endif
            @if ($user->supervisor && $user->supervisor->external_id)
                <div class="flex items-center gap-2 text-sm">
                    <x-ui.icon name="user" variant="mini" class="text-mist-400" />
                    <a href="{{ route('people.show', $user->supervisor) }}" class="truncate hover:underline" wire:navigate>{{ $user->supervisor->display_name }}</a>
                </div>
            @endif
            @if ($whereaboutsToday)
                <div class="flex items-center gap-2 text-sm">
                    <x-ui.text size="sm">{{ __('Location today') }}:</x-ui.text>
                    <x-whereabouts-badge :entry="$whereaboutsToday" :reveal="$isSelf" />
                </div>
            @endif

            <x-ui.separator />

            <x-ui.heading size="sm">{{ __('Groups') }}</x-ui.heading>
            @forelse ($groups as $group)
                <div wire:key="group-{{ $group->id }}" class="flex items-center gap-2 text-sm">
                    <x-ui.icon name="user-group" variant="mini" class="text-mist-400" />
                    <span class="truncate">{{ $group->name }}</span>
                </div>
            @empty
                <x-ui.text size="sm">{{ __('No shared groups.') }}</x-ui.text>
            @endforelse
        </x-ui.card>

        {{-- Recent posts --}}
        <div class="space-y-4 md:col-span-2">
            <x-ui.heading size="sm">{{ __('Recent posts') }}</x-ui.heading>
            @forelse ($posts as $post)
                @php($images = $post->getMedia('images'))
                <x-ui.card wire:key="post-{{ $post->id }}" class="space-y-3">
                    <x-ui.text size="sm">
                        @if ($post->group)<span class="font-medium text-brand-700 dark:text-brand-400">{{ $post->group->name }}</span> · @endif{{ $post->published_at?->diffForHumans() }}
                    </x-ui.text>

                    @if (trim($post->body) !== '')
                        <div class="prose prose-sm max-w-none whitespace-pre-line dark:prose-invert">{{ $post->body }}</div>
                    @endif

                    @if ($images->isNotEmpty())
                        <div @class([
                            'grid gap-2',
                            'grid-cols-1' => $images->count() === 1,
                            'grid-cols-2' => $images->count() === 2,
                            'grid-cols-3' => $images->count() >= 3,
                        ])>
                            @foreach ($images->take(3) as $image)
                                <img wire:key="post-img-{{ $image->id }}" src="{{ $image->getUrl() }}" alt="{{ $image->name }}"
                                    class="w-full rounded-2xl object-cover {{ $images->count() === 1 ? 'max-h-80' : 'h-28' }}" />
                            @endforeach
                        </div>
                    @endif

                    <div class="flex items-center gap-4 text-sm text-mist-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="hand-thumb-up" variant="micro" />
                            {{ $post->reactions_count }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="chat-bubble-oval-left" variant="micro" />
                            {{ $post->comments_count }}
                        </span>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.alert icon="document-text">{{ __('No posts to show.') }}</x-ui.alert>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        <livewire:pages::people.calendar :user="$user" wire:key="calendar-{{ $user->id }}" />
    </div>
</div>
