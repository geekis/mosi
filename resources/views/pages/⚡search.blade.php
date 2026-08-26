<?php

use App\Actions\Search\SearchEverything;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Search')] class extends Component {
    #[Url(as: 'q')]
    public string $q = '';

    /**
     * @return array{results: array{people: \Illuminate\Support\Collection, groups: \Illuminate\Support\Collection, posts: \Illuminate\Support\Collection, events: \Illuminate\Support\Collection}, hasResults: bool}
     */
    public function with(SearchEverything $searchEverything): array
    {
        $results = $searchEverything(Auth::user(), $this->q);

        return [
            'results' => $results,
            'hasResults' => collect($results)->contains(fn ($set) => $set->isNotEmpty()),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Search') }}</flux:heading>

    <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" :placeholder="__('Search Mosi…')" class="mt-4" autofocus />

    @if (trim($q) === '')
        <flux:text class="mt-6 text-zinc-500 dark:text-zinc-400">{{ __('Search people, groups, posts and events.') }}</flux:text>
    @elseif (! $hasResults)
        <flux:callout icon="magnifying-glass" class="mt-6">{{ __('Nothing found for “:term”.', ['term' => $q]) }}</flux:callout>
    @else
        {{-- People --}}
        @if ($results['people']->isNotEmpty())
            <flux:heading size="sm" class="mt-6">{{ __('People') }}</flux:heading>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($results['people'] as $person)
                    <a wire:key="s-person-{{ $person->id }}" href="{{ $person->external_id ? route('people.show', $person) : '#' }}" wire:navigate
                       class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <flux:avatar :name="$person->display_name" :initials="$person->initials()" :src="$person->avatarUrl()" size="sm" />
                        <div class="min-w-0">
                            <flux:text class="truncate font-medium">{{ $person->display_name }}</flux:text>
                            @if ($person->job_title)<flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $person->job_title }}</flux:text>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Events --}}
        @if ($results['events']->isNotEmpty())
            <flux:heading size="sm" class="mt-6">{{ __('Events') }}</flux:heading>
            <div class="mt-2 space-y-2">
                @foreach ($results['events'] as $event)
                    <a wire:key="s-event-{{ $event->id }}" href="{{ route('events.show', $event) }}" wire:navigate
                       class="block rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <flux:text class="font-medium">{{ $event->title }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $event->starts_at->translatedFormat('j. M Y, H:i') }}</flux:text>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Groups --}}
        @if ($results['groups']->isNotEmpty())
            <flux:heading size="sm" class="mt-6">{{ __('Groups') }}</flux:heading>
            <div class="mt-2 space-y-2">
                @foreach ($results['groups'] as $group)
                    <div wire:key="s-group-{{ $group->id }}" class="flex items-center gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:icon icon="user-group" variant="mini" class="text-zinc-400" />
                        <flux:text class="truncate font-medium">{{ $group->name }}</flux:text>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Posts --}}
        @if ($results['posts']->isNotEmpty())
            <flux:heading size="sm" class="mt-6">{{ __('Posts') }}</flux:heading>
            <div class="mt-2 space-y-2">
                @foreach ($results['posts'] as $post)
                    <div wire:key="s-post-{{ $post->id }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ $post->author?->display_name }}@if ($post->group) · {{ $post->group->name }}@endif
                        </flux:text>
                        <div class="prose prose-sm mt-1 max-w-none whitespace-pre-line dark:prose-invert">{{ $post->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
