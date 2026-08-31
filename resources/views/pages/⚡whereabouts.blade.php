<?php

use App\Actions\Whereabouts\SetWhereabouts;
use App\Enums\AbsenceType;
use App\Models\WorkLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Whereabouts')] class extends Component {
    /**
     * Any day in the week being edited — kept in the URL so a week can be linked to. The
     * editor always shows the Monday–Sunday week containing it.
     */
    #[Url]
    public string $date = '';

    public function mount(): void
    {
        if (! $this->isValidDate($this->date)) {
            $this->date = now()->toDateString();
        }
    }

    public function goToToday(): void
    {
        $this->date = now()->toDateString();
    }

    public function shiftWeek(int $weeks): void
    {
        $this->date = $this->anchor()->addWeeks($weeks)->toDateString();
    }

    /**
     * Register my own whereabouts at a work location for a day in the visible week. Both
     * the day and the location id come from the client, so both are re-checked: the day
     * must fall inside the editable week and the location must be an active one.
     */
    public function setLocation(string $day, int $locationId, SetWhereabouts $setWhereabouts): void
    {
        $date = $this->editableDay($day);
        $location = WorkLocation::query()->active()->whereKey($locationId)->first();

        if ($date === null || $location === null) {
            return;
        }

        $setWhereabouts(Auth::user(), $date, location: $location);
    }

    /**
     * Register my own absence (Orlof / Veikindi) for a day in the visible week.
     */
    public function setAbsence(string $day, string $reason, SetWhereabouts $setWhereabouts): void
    {
        $date = $this->editableDay($day);
        $absence = AbsenceType::tryFrom($reason);

        if ($date === null || $absence === null) {
            return;
        }

        $setWhereabouts(Auth::user(), $date, absence: $absence);
    }

    public function clearDay(string $day, SetWhereabouts $setWhereabouts): void
    {
        $date = $this->editableDay($day);

        if ($date !== null) {
            $setWhereabouts(Auth::user(), $date);
        }
    }

    private function anchor(): Carbon
    {
        return Carbon::parse($this->date)->startOfDay();
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resolve a client-supplied day string to a Carbon only when it is a real date inside
     * the week currently shown in the editor — the only days this view lets you edit.
     */
    private function editableDay(string $day): ?Carbon
    {
        if (! $this->isValidDate($day)) {
            return null;
        }

        $date = Carbon::parse($day)->startOfDay();

        return $date->between($this->anchor()->startOfWeek(), $this->anchor()->endOfWeek())
            ? $date
            : null;
    }

    /**
     * @return array{weekStart: Carbon, weekEnd: Carbon, isThisWeek: bool, days: Collection<int, array{date: string, carbon: Carbon, entry: ?\App\Models\Whereabouts, isToday: bool}>, locations: Collection<int, WorkLocation>, absences: array<int, AbsenceType>}
     */
    public function with(): array
    {
        $me = Auth::user();
        $weekStart = $this->anchor()->startOfWeek();
        $weekEnd = $this->anchor()->endOfWeek();
        $today = now()->toDateString();

        // My registrations across the visible week, keyed by day for a cheap per-day lookup.
        $myWeek = $me->whereabouts()
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with('workLocation')
            ->get()
            ->keyBy(fn (\App\Models\Whereabouts $w): string => $w->date->toDateString());

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $myWeek, $today): array {
            $date = $weekStart->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'carbon' => $date,
                'entry' => $myWeek->get($date->toDateString()),
                'isToday' => $date->toDateString() === $today,
            ];
        });

        return [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'isThisWeek' => now()->betweenIncluded($weekStart, $weekEnd),
            'days' => $days,
            'locations' => WorkLocation::query()->active()->get(),
            'absences' => AbsenceType::cases(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-4xl">
    <x-ui.heading size="xl">{{ __('Whereabouts') }}</x-ui.heading>

    <x-ui.card class="mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-ui.heading size="lg">{{ __('My week') }}</x-ui.heading>
            <div class="flex items-center gap-1">
                <x-ui.button size="sm" variant="ghost" icon="chevron-left"
                    wire:click="shiftWeek(-1)" :aria-label="__('Previous week')" />
                <x-ui.text size="sm" class="min-w-32 text-center">
                    {{ $weekStart->isoFormat('D.MM.') }}–{{ $weekEnd->isoFormat('D.MM.YYYY') }}
                </x-ui.text>
                <x-ui.button size="sm" variant="ghost" icon="chevron-right"
                    wire:click="shiftWeek(1)" :aria-label="__('Next week')" />
                <x-ui.button size="sm" variant="ghost" wire:click="goToToday" :disabled="$isThisWeek">
                    {{ __('Today') }}
                </x-ui.button>
            </div>
        </div>

        <x-ui.text size="sm" class="mt-1">
            {{ __('Register where you are working each day. Colleagues see your location — an absence shows only as „Fjarverandi“.') }}
        </x-ui.text>

        <div class="mt-4 space-y-2">
            @foreach ($days as $day)
                <div wire:key="day-{{ $day['date'] }}"
                    @class([
                        'flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between',
                        'border-sand-200 dark:border-zinc-700' => ! $day['isToday'],
                        'border-brand-300 bg-brand-50/50 dark:border-brand-700 dark:bg-brand-900/10' => $day['isToday'],
                    ])>
                    <div class="min-w-0">
                        <x-ui.text class="font-medium capitalize text-ink-800 dark:text-zinc-200">{{ $day['carbon']->isoFormat('dddd D.MM.') }}</x-ui.text>
                        <div class="mt-0.5">
                            {{-- My own week, so reveal the real absence reason to me. --}}
                            <x-whereabouts-badge :entry="$day['entry']" reveal />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1">
                        <x-ui.select class="w-44"
                            x-on:change="$wire.setLocation('{{ $day['date'] }}', $event.target.value)">
                            <x-ui.select.option value="">{{ __('Select a location') }}</x-ui.select.option>
                            @foreach ($locations as $location)
                                <x-ui.select.option :value="$location->id"
                                    :selected="$day['entry'] && ! $day['entry']->isAbsence() && $day['entry']->work_location_id === $location->id">
                                    {{ $location->name }}
                                </x-ui.select.option>
                            @endforeach
                        </x-ui.select>

                        @foreach ($absences as $absence)
                            <x-ui.button size="xs"
                                :variant="$day['entry']?->absence === $absence ? 'filled' : 'ghost'"
                                wire:click="setAbsence('{{ $day['date'] }}', '{{ $absence->value }}')">
                                {{ $absence->label() }}
                            </x-ui.button>
                        @endforeach

                        <x-ui.button size="xs" variant="ghost" icon="x-mark"
                            wire:click="clearDay('{{ $day['date'] }}')" :aria-label="__('Clear')" />
                    </div>
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>
