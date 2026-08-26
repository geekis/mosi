@props(['post', 'showGroup' => true, 'mentionables' => [], 'mentionMap' => []])

@use('App\Enums\ReactionType')

@php
    $images = $post->getMedia('images');
    $videos = $post->getMedia('videos');
    $files = $post->getMedia('attachments');

    // Reaction breakdown, computed from the eager-loaded rows so there is no extra query.
    // Read the raw stored value and tryFrom() it, so a legacy value from before the
    // palette changed is skipped rather than throwing on the enum cast.
    $reactionSummary = [];
    foreach ($post->reactions as $reaction) {
        $type = ReactionType::tryFrom($reaction->getRawOriginal('type'));
        if ($type !== null) {
            $reactionSummary[$type->value] = ($reactionSummary[$type->value] ?? 0) + 1;
        }
    }
    arsort($reactionSummary);
    $totalReactions = array_sum($reactionSummary);

    $myReactionRaw = $post->reactions->firstWhere('user_id', auth()->id())?->getRawOriginal('type');
    $myReaction = $myReactionRaw !== null ? ReactionType::tryFrom($myReactionRaw) : null;

    $comments = $post->comments;
    $commentCount = $post->comments_count ?? $comments->count();
@endphp

{{--
    A single post in the feed or a group. Media opens in the shared fullscreen viewer via
    the ancestor Alpine `open()`. Reactions show a Facebook-style summary that opens the
    "who reacted" modal (host `showReactions()`); the Like button carries a hover palette
    and reflects the viewer's own reaction. Comments expand inline with a plain-text box.
    All host methods (react / showReactions / addComment) come from the
    InteractsWithPostComposer trait.
--}}
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
                @if ($showGroup && $post->group)
                    <a href="{{ route('groups.show', $post->group) }}" wire:navigate class="truncate hover:underline">{{ $post->group->name }}</a>
                    <span>·</span>
                @endif
                <span>{{ $post->published_at?->diffForHumans() }}</span>
                @if ($post->is_official)
                    <flux:badge size="sm" color="green">{{ __('Official') }}</flux:badge>
                @endif
            </flux:text>
        </div>
    </div>

    @if (trim($post->body) !== '')
        <x-post-body :body="$post->body" :mentions="$mentionMap" class="prose prose-sm mt-3 max-w-none break-words dark:prose-invert" />
    @endif

    {{-- Images and videos in a square grid --}}
    @if ($images->isNotEmpty() || $videos->isNotEmpty())
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach ($images as $image)
                <button type="button" wire:key="post-{{ $post->id }}-img-{{ $image->id }}"
                    x-on:click="open('image', @js($image->getUrl()), @js($image->name))"
                    class="cursor-zoom-in overflow-hidden rounded-xl">
                    <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}" loading="lazy"
                        class="aspect-square w-full object-cover hover:opacity-90" />
                </button>
            @endforeach

            @foreach ($videos as $video)
                <button type="button" wire:key="post-{{ $post->id }}-video-{{ $video->id }}"
                    x-on:click="open('video', @js($video->getUrl()), @js($video->name))"
                    class="group relative cursor-zoom-in overflow-hidden rounded-xl">
                    <video src="{{ $video->getUrl() }}#t=0.1" preload="metadata"
                        class="aspect-square w-full object-cover" muted playsinline></video>
                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/25 transition group-hover:bg-black/35">
                        <flux:icon icon="play-circle" class="size-10 text-white/90" />
                    </span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Downloadable files --}}
    @if ($files->isNotEmpty())
        <div class="mt-3 space-y-1">
            @foreach ($files as $file)
                <a wire:key="post-{{ $post->id }}-file-{{ $file->id }}" href="{{ $file->getUrl() }}" target="_blank" rel="noopener"
                    class="flex items-center gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-sm hover:bg-zinc-100 dark:bg-zinc-800/50 dark:hover:bg-zinc-800">
                    <flux:icon icon="paper-clip" variant="micro" class="shrink-0 text-zinc-400" />
                    <span class="truncate">{{ $file->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Reaction summary + comment count --}}
    @if ($totalReactions > 0 || $commentCount > 0)
        <div class="mt-3 flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400">
            @if ($totalReactions > 0)
                <button type="button" wire:click="showReactions({{ $post->id }})" class="flex items-center gap-1.5 hover:underline">
                    <span class="flex items-center">
                        @foreach (array_slice(array_keys($reactionSummary), 0, 3) as $value)
                            <span class="-ml-0.5 first:ml-0">{{ ReactionType::from($value)->emoji() }}</span>
                        @endforeach
                    </span>
                    <span>{{ $totalReactions }}</span>
                </button>
            @else
                <span></span>
            @endif

            @if ($commentCount > 0)
                <span>{{ __(':count comments', ['count' => $commentCount]) }}</span>
            @endif
        </div>
    @endif

    <flux:separator class="my-2" variant="subtle" />

    {{-- Action row + comments share one Alpine scope so the Comment button toggles the panel. --}}
    <div x-data="{ showPalette: false, showComments: false }">
        <div class="flex items-center gap-1">
            <div class="relative flex-1" x-on:mouseenter="showPalette = true" x-on:mouseleave="showPalette = false">
                <div x-show="showPalette" x-cloak x-transition
                    class="absolute bottom-full left-0 z-20 mb-1 flex gap-1 rounded-full border border-zinc-200 bg-white px-2 py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                    @foreach (ReactionType::cases() as $reaction)
                        <button type="button" wire:key="post-{{ $post->id }}-react-{{ $reaction->value }}"
                            wire:click="react({{ $post->id }}, '{{ $reaction->value }}')"
                            x-on:click="showPalette = false"
                            class="text-xl leading-none transition hover:scale-125"
                            title="{{ $reaction->label() }}" aria-label="{{ $reaction->label() }}">{{ $reaction->emoji() }}</button>
                    @endforeach
                </div>

                {{-- Clicking toggles the viewer's current reaction, or adds a Like. --}}
                <button type="button" wire:click="react({{ $post->id }}, '{{ $myReaction?->value ?? 'like' }}')"
                    @class([
                        'flex w-full items-center justify-center gap-1.5 rounded-lg py-1.5 text-sm font-medium transition hover:bg-zinc-100 dark:hover:bg-zinc-800',
                        'text-primary-600 dark:text-primary-400' => $myReaction !== null,
                        'text-zinc-500 dark:text-zinc-400' => $myReaction === null,
                    ])>
                    @if ($myReaction !== null)
                        <span class="text-base leading-none">{{ $myReaction->emoji() }}</span>
                        <span>{{ $myReaction->label() }}</span>
                    @else
                        <flux:icon icon="hand-thumb-up" variant="micro" />
                        <span>{{ __('Like') }}</span>
                    @endif
                </button>
            </div>

            <button type="button" x-on:click="showComments = ! showComments"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-1.5 text-sm font-medium text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                <flux:icon icon="chat-bubble-oval-left-ellipsis" variant="micro" />
                <span>{{ __('Comment') }}</span>
            </button>
        </div>

        {{-- Comments panel. Eager-loaded, so opening is instant; adding goes through addComment(). --}}
        <div x-show="showComments" x-cloak class="mt-3 space-y-3">
            @foreach ($comments as $comment)
                <div wire:key="comment-{{ $comment->id }}" class="flex gap-2">
                    <flux:avatar :name="$comment->author?->display_name" :src="$comment->author?->avatarUrl()" size="xs" class="mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <div class="rounded-2xl bg-zinc-100 px-3 py-2 dark:bg-zinc-800">
                            @if ($comment->author?->external_id)
                                <a href="{{ route('people.show', $comment->author) }}" wire:navigate class="text-sm font-medium hover:underline">{{ $comment->author->display_name }}</a>
                            @else
                                <span class="text-sm font-medium">{{ $comment->author?->display_name }}</span>
                            @endif
                            <x-post-body :body="$comment->body" :mentions="$mentionMap" class="break-words text-sm" />
                        </div>
                        <flux:text size="sm" class="mt-0.5 px-3 text-zinc-400 dark:text-zinc-500">{{ $comment->created_at?->diffForHumans() }}</flux:text>
                    </div>
                </div>
            @endforeach

            {{-- Add a comment --}}
            <div class="flex items-start gap-2">
                <flux:avatar :name="auth()->user()->display_name" :initials="auth()->user()->initials()" :src="auth()->user()->avatarUrl()" size="xs" class="mt-0.5 shrink-0" />
                <div class="min-w-0 flex-1">
                    <x-mention-textarea wire:model="commentDrafts.{{ $post->id }}" :rows="2"
                        :placeholder="__('Write a comment…')" :mentionables="$mentionables" />
                    <div class="mt-1 flex justify-end">
                        <flux:button size="sm" variant="primary" wire:click="addComment({{ $post->id }})"
                            data-test="add-comment-{{ $post->id }}">{{ __('Comment') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</flux:card>
