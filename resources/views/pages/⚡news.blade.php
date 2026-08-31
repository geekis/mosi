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
    <x-ui.heading size="xl">{{ __('News') }}</x-ui.heading>

    <div class="mt-6 space-y-4">
        @forelse ($posts as $post)
            <x-ui.card wire:key="news-{{ $post->id }}" class="ring-1 ring-brand-500/40">
                <div class="flex items-center gap-3">
                    <x-avatar :user="$post->author" size="sm" />
                    <div class="min-w-0">
                        <x-ui.heading class="truncate">{{ $post->author?->display_name }}</x-ui.heading>
                        <x-ui.text size="sm" class="flex items-center gap-1">
                            {{ $post->published_at?->diffForHumans() }}
                            <x-ui.badge size="sm" color="green">{{ __('Official') }}</x-ui.badge>
                        </x-ui.text>
                    </div>
                </div>
                <div class="prose prose-sm mt-3 max-w-none whitespace-pre-line dark:prose-invert">{{ $post->body }}</div>
            </x-ui.card>
        @empty
            <x-ui.alert icon="newspaper">{{ __('No news yet.') }}</x-ui.alert>
        @endforelse
    </div>

    <div class="mt-6">{{ $posts->links() }}</div>
</div>
