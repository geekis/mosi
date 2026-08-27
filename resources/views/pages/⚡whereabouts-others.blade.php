<?php

use App\Actions\Whereabouts\BuildWhereaboutsBoard;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Whereabouts')] class extends Component {
    /** The day whose board is shown. Kept in the URL so a day can be linked to. */
    #[Url]
    public string $date = '';

    /** Work-location filter (id as string; '' means all locations). */
    #[Url]
    public string $location = '';

    /** Division filter, matched against organization_name; '' means all divisions. */
    #[Url]
    public string $division = '';

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

    public function shiftDay(int $days): void
    {
        $this->date = $this->selectedDate()->addDays($days)->toDateString();
    }

    private function selectedDate(): Carbon
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
     * Distinct divisions for the filter, from active staff only — mirrors the directory.
     *
     * @return array<int, string>
     */
    public function divisions(): array
    {
        return User::query()
            ->active()
            ->whereNotNull('organization_name')
            ->distinct()
            ->orderBy('organization_name')
            ->pluck('organization_name')
            ->all();
    }

    /**
     * @return array{selected: Carbon, isToday: bool, board: Collection<int, \App\Models\Whereabouts>, locations: Collection<int, WorkLocation>, divisions: array<int, string>}
     */
    public function with(BuildWhereaboutsBoard $buildBoard): array
    {
        $me = Auth::user();
        $selected = $this->selectedDate();

        return [
            'selected' => $selected,
            'isToday' => $selected->isToday(),
            'board' => $buildBoard($selected, [
                'location_id' => $this->location !== '' ? (int) $this->location : null,
                'division' => $this->division !== '' ? $this->division : null,
                // "Other staff" — never me.
                'exclude_user_id' => $me->id,
            ]),
            'locations' => WorkLocation::query()->active()->get(),
            'divisions' => $this->divisions(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-4xl">
    <flux:heading size="xl">{{ __('Other staff') }}</flux:heading>

    {{-- Day selector --}}
    <div class="mt-4 flex items-center justify-between gap-2">
        <flux:heading size="lg" class="capitalize">{{ $selected->isoFormat('dddd D.MM.YYYY') }}</flux:heading>
        <div class="flex items-center gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left"
                wire:click="shiftDay(-1)" :aria-label="__('Previous day')" />
            <flux:button size="sm" variant="ghost" wire:click="goToToday" :disabled="$isToday">
                {{ __('Today') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="chevron-right"
                wire:click="shiftDay(1)" :aria-label="__('Next day')" />
        </div>
    </div>

    {{-- Filters --}}
    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
        <flux:select wire:model.live="location" :placeholder="__('All locations')" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('All locations') }}</flux:select.option>
            @foreach ($locations as $location)
                <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="division" :placeholder="__('All divisions')" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
            @foreach ($divisions as $name)
                <flux:select.option :value="$name">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Board --}}
    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @forelse ($board as $entry)
            <flux:card size="sm" wire:key="board-{{ $entry->id }}" class="flex items-center gap-3">
                <flux:avatar :name="$entry->user->display_name" :initials="$entry->user->initials()"
                    :src="$entry->user->avatarUrl()" size="sm" />
                <div class="min-w-0 flex-1">
                    @if ($entry->user->external_id)
                        <a href="{{ route('people.show', $entry->user) }}" wire:navigate class="hover:underline">
                            <flux:text class="truncate font-medium">{{ $entry->user->display_name }}</flux:text>
                        </a>
                    @else
                        <flux:text class="truncate font-medium">{{ $entry->user->display_name }}</flux:text>
                    @endif
                    @if ($entry->user->organization_name)
                        <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $entry->user->organization_name }}</flux:text>
                    @endif
                    {{-- These are always other people, so an absence reason is never revealed. --}}
                    <x-whereabouts-badge :entry="$entry" class="mt-0.5" />
                </div>
            </flux:card>
        @empty
            <flux:callout icon="map-pin" class="sm:col-span-2">
                {{ __('No one has registered for this day yet.') }}
            </flux:callout>
        @endforelse
    </div>
</div>
