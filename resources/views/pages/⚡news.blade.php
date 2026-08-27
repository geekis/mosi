<?php

use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('News')] class extends Component {
    use WithPagination;

    /**
     * @return array{posts: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function with(): array
    {
        return [
            // Official posts only, still visibility-scoped, newest first.
            'posts' => Post::query()
                ->visibleTo(Auth::user())
                ->published()
                ->where('is_official', true)
                ->with(['author.media', 'group'])
                ->withCount(['reactions', 'comments'])
                ->latest('published_at')
                ->paginate(10),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ __('News') }}</flux:heading>

    <div class="mt-6 space-y-4">
        @forelse ($posts as $post)
            <flux:card wire:key="news-{{ $post->id }}" class="ring-1 ring-primary-500/40">
                <div class="flex items-center gap-3">
                    <x-avatar :user="$post->author" size="sm" />
                    <div class="min-w-0">
                        <flux:heading class="truncate">{{ $post->author?->display_name }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ $post->published_at?->diffForHumans() }}
                            <flux:badge size="sm" color="green">{{ __('Official') }}</flux:badge>
                        </flux:text>
                    </div>
                </div>
                <div class="prose prose-sm mt-3 max-w-none whitespace-pre-line dark:prose-invert">{{ $post->body }}</div>
            </flux:card>
        @empty
            <flux:callout icon="newspaper">{{ __('No news yet.') }}</flux:callout>
        @endforelse
    </div>

    <div class="mt-6">{{ $posts->links() }}</div>
</div>
