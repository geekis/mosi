<?php

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

    /**
     * @return array{isSelf: bool, isFollowing: bool, groups: \Illuminate\Support\Collection<int, \App\Models\Group>, posts: \Illuminate\Support\Collection<int, Post>}
     */
    public function with(): array
    {
        $viewer = Auth::user();

        return [
            'isSelf' => $viewer->is($this->user),
            'isFollowing' => $viewer->isFollowing($this->user),
            // Secret groups the viewer cannot see never appear on someone else's profile.
            'groups' => $this->user->groups()->visibleTo($viewer)->orderBy('id')->get(),
            'posts' => Post::query()
                ->visibleTo($viewer)
                ->where('user_id', $this->user->id)
                ->published()
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
    <flux:card class="overflow-hidden p-0">
        <div class="h-40 w-full bg-gradient-to-br from-primary-600 to-primary-800">
            @if ($user->bannerUrl())
                <img src="{{ $user->bannerUrl() }}" alt="" class="h-40 w-full object-cover" />
            @endif
        </div>

        <div class="px-6 pb-6">
            <div class="-mt-12 flex items-end justify-between">
                <flux:avatar :name="$user->display_name" :initials="$user->initials()" :src="$user->avatarUrl()" size="xl" class="ring-4 ring-white dark:ring-zinc-800" />

                <div class="mb-1 flex items-center gap-2">
                    @if ($isSelf)
                        <flux:button :href="route('profile.edit')" size="sm" variant="ghost" icon="pencil-square" wire:navigate>
                            {{ __('Edit profile') }}
                        </flux:button>
                    @elseif ($isFollowing)
                        <flux:button size="sm" variant="ghost" icon="user-minus" wire:click="unfollow">
                            {{ __('Following') }}
                        </flux:button>
                    @else
                        <flux:button size="sm" variant="primary" icon="user-plus" wire:click="follow">
                            {{ __('Follow') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="mt-3">
                <flux:heading size="xl">{{ $user->display_name }}</flux:heading>
                @if ($user->job_title)
                    <flux:text class="mt-1">{{ $user->job_title }}</flux:text>
                @endif
                @if ($user->organization_name)
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $user->organization_name }}</flux:text>
                @endif
            </div>

            @if ($user->bio)
                <flux:text class="mt-4 whitespace-pre-line">{{ $user->bio }}</flux:text>
            @endif
        </div>
    </flux:card>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        {{-- Contact & org details --}}
        <flux:card class="space-y-3 md:col-span-1">
            <flux:heading size="sm">{{ __('Details') }}</flux:heading>

            @if ($user->email)
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon icon="envelope" variant="mini" class="text-zinc-400" />
                    <a href="mailto:{{ $user->email }}" class="truncate hover:underline">{{ $user->email }}</a>
                </div>
            @endif
            @if ($user->phone)
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon icon="phone" variant="mini" class="text-zinc-400" />
                    <a href="tel:{{ $user->phone }}" class="truncate hover:underline">{{ $user->phone }}</a>
                </div>
            @endif
            @if ($user->supervisor && $user->supervisor->external_id)
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon icon="user" variant="mini" class="text-zinc-400" />
                    <a href="{{ route('people.show', $user->supervisor) }}" class="truncate hover:underline" wire:navigate>{{ $user->supervisor->display_name }}</a>
                </div>
            @endif

            <flux:separator variant="subtle" />

            <flux:heading size="sm">{{ __('Groups') }}</flux:heading>
            @forelse ($groups as $group)
                <div wire:key="group-{{ $group->id }}" class="flex items-center gap-2 text-sm">
                    <flux:icon icon="user-group" variant="mini" class="text-zinc-400" />
                    <span class="truncate">{{ $group->name }}</span>
                </div>
            @empty
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No shared groups.') }}</flux:text>
            @endforelse
        </flux:card>

        {{-- Recent posts --}}
        <div class="space-y-4 md:col-span-2">
            <flux:heading size="sm">{{ __('Recent posts') }}</flux:heading>
            @forelse ($posts as $post)
                <flux:card wire:key="post-{{ $post->id }}">
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        @if ($post->group){{ $post->group->name }} · @endif{{ $post->published_at?->diffForHumans() }}
                    </flux:text>
                    <div class="prose prose-sm mt-2 max-w-none whitespace-pre-line dark:prose-invert">{!! $post->body !!}</div>
                    <flux:text size="sm" class="mt-3 text-zinc-500 dark:text-zinc-400">
                        {{ $post->reactions_count }} · {{ $post->comments_count }}
                    </flux:text>
                </flux:card>
            @empty
                <flux:callout icon="document-text">{{ __('No posts to show.') }}</flux:callout>
            @endforelse
        </div>
    </div>
</div>
