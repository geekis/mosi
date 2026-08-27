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
     * @return array{hasCalendar: bool, rangeStart: CarbonImmutable, rangeEnd: CarbonImmutable, eventsByDay: Collection<string, Collection<int, array<string, mixed>>>}
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
            'rangeStart' => $start,
            'rangeEnd' => $end->subDay(),
            'eventsByDay' => $eventsByDay,
        ];
    }
}; ?>

<flux:card class="space-y-4">
    <div class="flex items-center justify-between gap-2">
        <flux:heading size="sm">{{ __('Calendar') }}</flux:heading>
        @if ($hasCalendar)
            <div class="flex items-center gap-1">
                <flux:button size="xs" variant="ghost" icon="chevron-left" wire:click="previousWeek" :aria-label="__('Previous week')" />
                <flux:button size="xs" variant="ghost" wire:click="thisWeek">{{ __('This week') }}</flux:button>
                <flux:button size="xs" variant="ghost" icon="chevron-right" wire:click="nextWeek" :aria-label="__('Next week')" />
            </div>
        @endif
    </div>

    @if (! $hasCalendar)
        <flux:callout icon="calendar-days">{{ __('No calendar available for this person.') }}</flux:callout>
    @else
        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
            {{ $rangeStart->translatedFormat('j. M') }} – {{ $rangeEnd->translatedFormat('j. M Y') }}
        </flux:text>

        @forelse ($eventsByDay as $day => $events)
            @php($date = \Carbon\CarbonImmutable::parse($day))
            <div wire:key="cal-day-{{ $day }}">
                <flux:heading size="xs" class="mb-1 text-zinc-500 dark:text-zinc-400">
                    {{ $date->translatedFormat('l j. M') }}
                </flux:heading>
                <div class="space-y-1.5">
                    @foreach ($events as $index => $event)
                        <div wire:key="cal-event-{{ $day }}-{{ $index }}"
                            @class([
                                'rounded-lg border-l-2 bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50',
                                'border-green-500' => $event['show_as'] === 'free',
                                'border-amber-500' => $event['show_as'] === 'tentative',
                                'border-red-500' => $event['show_as'] === 'busy',
                                'border-purple-500' => $event['show_as'] === 'oof',
                                'border-blue-500' => $event['show_as'] === 'workingElsewhere',
                                'border-zinc-400' => ! in_array($event['show_as'], ['free', 'tentative', 'busy', 'oof', 'workingElsewhere'], true),
                            ])>
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-medium">{{ $event['subject'] ?? __('(No subject)') }}</span>
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if ($event['is_all_day'])
                                        {{ __('All day') }}
                                    @else
                                        {{ $event['start']->format('H:i') }}–{{ $event['end']->format('H:i') }}
                                    @endif
                                </span>
                            </div>
                            @if ($event['location'])
                                <div class="mt-0.5 flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    <flux:icon icon="map-pin" variant="outline" class="size-3 shrink-0" />
                                    <span class="truncate">{{ $event['location'] }}</span>
                                </div>
                            @endif
                            @if (! empty($event['attendees']))
                                <div class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ implode(', ', array_slice($event['attendees'], 0, 5)) }}{{ count($event['attendees']) > 5 ? '…' : '' }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:callout icon="calendar-days">{{ __('No events this week.') }}</flux:callout>
        @endforelse
    @endif
</flux:card>
