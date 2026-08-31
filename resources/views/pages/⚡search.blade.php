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
    <x-ui.heading size="xl">{{ __('Search') }}</x-ui.heading>

    <x-ui.input wire:model.live.debounce.300ms="q" icon="magnifying-glass" :placeholder="__('Search Mosi…')" class="mt-4" autofocus />

    @if (trim($q) === '')
        <x-ui.text class="mt-6">{{ __('Search people, groups, posts and events.') }}</x-ui.text>
    @elseif (! $hasResults)
        <x-ui.alert icon="magnifying-glass" class="mt-6">{{ __('Nothing found for “:term”.', ['term' => $q]) }}</x-ui.alert>
    @else
        {{-- People --}}
        @if ($results['people']->isNotEmpty())
            <x-ui.heading size="sm" class="mt-6">{{ __('People') }}</x-ui.heading>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($results['people'] as $person)
                    <a wire:key="s-person-{{ $person->id }}" href="{{ $person->external_id ? route('people.show', $person) : '#' }}" wire:navigate
                       class="flex items-center gap-3 rounded-xl border border-sand-200 p-3 hover:bg-sand-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <x-avatar :user="$person" size="sm" />
                        <div class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{ $person->display_name }}</span>
                            @if ($person->job_title)<x-ui.text size="sm" class="truncate">{{ $person->job_title }}</x-ui.text>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Events --}}
        @if ($results['events']->isNotEmpty())
            <x-ui.heading size="sm" class="mt-6">{{ __('Events') }}</x-ui.heading>
            <div class="mt-2 space-y-2">
                @foreach ($results['events'] as $event)
                    <a wire:key="s-event-{{ $event->id }}" href="{{ route('events.show', $event) }}" wire:navigate
                       class="block rounded-xl border border-sand-200 p-3 hover:bg-sand-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <span class="block text-sm font-medium">{{ $event->title }}</span>
                        <x-ui.text size="sm">{{ $event->starts_at->translatedFormat('j. M Y, H:i') }}</x-ui.text>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Groups --}}
        @if ($results['groups']->isNotEmpty())
            <x-ui.heading size="sm" class="mt-6">{{ __('Groups') }}</x-ui.heading>
            <div class="mt-2 space-y-2">
                @foreach ($results['groups'] as $group)
                    <div wire:key="s-group-{{ $group->id }}" class="flex items-center gap-2 rounded-xl border border-sand-200 p-3 dark:border-zinc-700">
                        <x-ui.icon name="user-group" variant="mini" class="text-mist-400" />
                        <span class="truncate text-sm font-medium">{{ $group->name }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Posts --}}
        @if ($results['posts']->isNotEmpty())
            <x-ui.heading size="sm" class="mt-6">{{ __('Posts') }}</x-ui.heading>
            <div class="mt-2 space-y-2">
                @foreach ($results['posts'] as $post)
                    <div wire:key="s-post-{{ $post->id }}" class="rounded-xl border border-sand-200 p-3 dark:border-zinc-700">
                        <x-ui.text size="sm">
                            {{ $post->author?->display_name }}@if ($post->group) · {{ $post->group->name }}@endif
                        </x-ui.text>
                        <div class="prose prose-sm mt-1 max-w-none whitespace-pre-line dark:prose-invert">{{ $post->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
