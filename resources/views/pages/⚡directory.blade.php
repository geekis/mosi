<?php

use App\Actions\Directory\BuildDirectory;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Directory')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $department = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        $this->resetPage();
    }

    /**
     * Distinct department names for the filter, from active staff only.
     *
     * @return array<int, string>
     */
    public function departments(): array
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
     * @return array{people: \Illuminate\Contracts\Pagination\LengthAwarePaginator, departments: array<int, string>}
     */
    public function with(BuildDirectory $buildDirectory): array
    {
        return [
            'people' => $buildDirectory(['search' => $this->search, 'department' => $this->department ?: null]),
            'departments' => $this->departments(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-5xl">
    <x-ui.heading size="xl">{{ __('Directory') }}</x-ui.heading>

    {{-- Filters --}}
    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
        <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search people…')" class="sm:max-w-xs" />

        <x-ui.select wire:model.live="department" :placeholder="__('All departments')" class="sm:max-w-xs">
            <x-ui.select.option value="">{{ __('All departments') }}</x-ui.select.option>
            @foreach ($departments as $name)
                <x-ui.select.option :value="$name">{{ $name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>
    </div>

    {{-- Grid --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($people as $person)
            <a wire:key="person-{{ $person->id }}" href="{{ $person->external_id ? route('people.show', $person) : '#' }}" wire:navigate>
                <x-ui.card size="sm" class="flex items-center gap-3 ">
                    <x-avatar :user="$person" size="lg" />
                    <div class="min-w-0">
                        <x-ui.heading size="sm" class="truncate">{{ $person->display_name }}</x-ui.heading>
                        @if ($person->job_title)
                            <x-ui.text size="sm" class="truncate text-ink-700 dark:text-zinc-300">{{ $person->job_title }}</x-ui.text>
                        @endif
                        @if ($person->organization_name)
                            <x-ui.text size="sm" class="truncate">{{ $person->organization_name }}</x-ui.text>
                        @endif
                        {{-- Today's whereabouts, if registered. Never reveals an absence reason here. --}}
                        <x-whereabouts-badge :entry="$person->whereaboutsOn(now())" class="mt-1" />
                    </div>
                </x-ui.card>
            </a>
        @empty
            <x-ui.alert icon="magnifying-glass" class="sm:col-span-2 lg:col-span-3">
                {{ __('No one matches your search.') }}
            </x-ui.alert>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $people->links() }}
    </div>
</div>
