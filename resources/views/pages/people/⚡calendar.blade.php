<?php

use App\Actions\Microsoft\FetchUserCalendar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * A read-only weekly agenda of a colleague's Outlook calendar, backed by Microsoft Graph
 * (app-only). Any signed-in user may view any colleague's calendar with full event detail;
 * for staff without an Entra mailbox, or while Graph is disabled, it shows an empty state.
 */
new class extends Component {
    public User $user;

    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = $this->currentWeekStart();
    }

    public function previousWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->weekStart = $this->currentWeekStart();
    }

    private function currentWeekStart(): string
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfWeek()->toDateString();
    }

    /**
     * @return array{hasCalendar: bool, isSelf: bool, rangeStart: CarbonImmutable, rangeEnd: CarbonImmutable, eventsByDay: Collection<string, Collection<int, array<string, mixed>>>}
     */
    public function with(): array
    {
        $start = CarbonImmutable::parse($this->weekStart, config('app.timezone'))->startOfDay();
        $end = $start->addWeek();

        $eventsByDay = collect(app(FetchUserCalendar::class)($this->user, $start, $end))
            ->sortBy(fn (array $event): int => $event['start']->getTimestamp())
            ->groupBy(fn (array $event): string => $event['start']->toDateString());

        return [
            'hasCalendar' => $this->user->entra_oid !== null && (bool) config('services.azure.graph.enabled'),
            // Only the owner sees the details of their own private events; to everyone else a
            // private event shows just its time and a "Private" label.
            'isSelf' => auth()->id() === $this->user->id,
            'rangeStart' => $start,
            'rangeEnd' => $end->subDay(),
            'eventsByDay' => $eventsByDay,
        ];
    }
}; ?>

<x-ui.card class="space-y-4">
    <div class="flex items-center justify-between gap-2">
        <x-ui.heading size="sm">{{ __('Calendar') }}</x-ui.heading>
        @if ($hasCalendar)
            <div class="flex items-center gap-1">
                <x-ui.button size="xs" variant="ghost" icon="chevron-left" wire:click="previousWeek" :aria-label="__('Previous week')" />
                <x-ui.button size="xs" variant="ghost" wire:click="thisWeek">{{ __('This week') }}</x-ui.button>
                <x-ui.button size="xs" variant="ghost" icon="chevron-right" wire:click="nextWeek" :aria-label="__('Next week')" />
            </div>
        @endif
    </div>

    @if (! $hasCalendar)
        <x-ui.alert icon="calendar-days">{{ __('No calendar available for this person.') }}</x-ui.alert>
    @else
        <x-ui.text size="sm">
            {{ $rangeStart->translatedFormat('j. M') }} – {{ $rangeEnd->translatedFormat('j. M Y') }}
        </x-ui.text>

        @forelse ($eventsByDay as $day => $events)
            @php($date = \Carbon\CarbonImmutable::parse($day))
            <div wire:key="cal-day-{{ $day }}">
                <x-ui.heading size="xs" class="mb-1 text-mist-500 dark:text-zinc-400">
                    {{ $date->translatedFormat('l j. M') }}
                </x-ui.heading>
                <div class="space-y-1.5">
                    @foreach ($events as $index => $event)
                        @php($private = ! $isSelf && ($event['sensitivity'] ?? 'normal') !== 'normal')
                        <div wire:key="cal-event-{{ $day }}-{{ $index }}"
                            @class([
                                'rounded-lg border-l-2 bg-sand-50 px-3 py-2 dark:bg-zinc-800/50',
                                'border-green-500' => $event['show_as'] === 'free',
                                'border-amber-500' => $event['show_as'] === 'tentative',
                                'border-red-500' => $event['show_as'] === 'busy',
                                'border-purple-500' => $event['show_as'] === 'oof',
                                'border-blue-500' => $event['show_as'] === 'workingElsewhere',
                                'border-mist-400' => ! in_array($event['show_as'], ['free', 'tentative', 'busy', 'oof', 'workingElsewhere'], true),
                            ])>
                            <div class="flex items-baseline justify-between gap-2">
                                @if ($private)
                                    <span class="flex items-center gap-1 truncate text-sm font-medium italic text-mist-500 dark:text-zinc-400">
                                        <x-ui.icon name="lock-closed" variant="micro" class="size-3 shrink-0" />
                                        {{ __('Private') }}
                                    </span>
                                @else
                                    <span class="truncate text-sm font-medium">{{ $event['subject'] ?? __('(No subject)') }}</span>
                                @endif
                                <span class="shrink-0 text-xs text-mist-500 dark:text-zinc-400">
                                    @if ($event['is_all_day'])
                                        {{ __('All day') }}
                                    @else
                                        {{ $event['start']->format('H:i') }}–{{ $event['end']->format('H:i') }}
                                    @endif
                                </span>
                            </div>
                            @if (! $private && $event['location'])
                                <div class="mt-0.5 flex items-center gap-1 text-xs text-mist-500 dark:text-zinc-400">
                                    <x-ui.icon name="map-pin" variant="outline" class="size-3 shrink-0" />
                                    <span class="truncate">{{ $event['location'] }}</span>
                                </div>
                            @endif
                            @if (! $private && ! empty($event['attendees']))
                                <div class="mt-0.5 truncate text-xs text-mist-500 dark:text-zinc-400">
                                    {{ implode(', ', array_slice($event['attendees'], 0, 5)) }}{{ count($event['attendees']) > 5 ? '…' : '' }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <x-ui.alert icon="calendar-days">{{ __('No events this week.') }}</x-ui.alert>
        @endforelse
    @endif
</x-ui.card>
