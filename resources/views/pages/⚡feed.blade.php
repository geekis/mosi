<?php

use App\Actions\Feed\BuildFeed;
use App\Actions\Posts\CreatePost;
use App\Actions\Reactions\React;
use App\Enums\ReactionType;
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
     * @return array{posts: \Illuminate\Contracts\Pagination\CursorPaginator}
     */
    public function with(BuildFeed $buildFeed): array
    {
        return ['posts' => $buildFeed(Auth::user(), 15)];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Feed') }}</flux:heading>

    {{-- Composer --}}
    <flux:card class="mt-4">
        <flux:editor wire:model="composer" :placeholder="__('Share something with your colleagues…')" />

        <div class="mt-3 flex justify-end">
            <flux:button variant="primary" wire:click="publish" data-test="publish-button">
                {{ __('Post') }}
            </flux:button>
        </div>
    </flux:card>

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
                    <flux:avatar :name="$post->author?->display_name" size="sm" />

                    <div class="min-w-0">
                        <flux:heading class="truncate">{{ $post->author?->display_name }}</flux:heading>

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
