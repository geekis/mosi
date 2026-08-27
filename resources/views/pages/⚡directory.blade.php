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
    <flux:heading size="xl">{{ __('Directory') }}</flux:heading>

    {{-- Filters --}}
    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search people…')" class="sm:max-w-xs" />

        <flux:select wire:model.live="department" :placeholder="__('All departments')" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
            @foreach ($departments as $name)
                <flux:select.option :value="$name">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Grid --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($people as $person)
            <a wire:key="person-{{ $person->id }}" href="{{ $person->external_id ? route('people.show', $person) : '#' }}" wire:navigate>
                <flux:card size="sm" class="flex items-center gap-3 ">
                    <x-avatar :user="$person" size="lg" />
                    <div class="min-w-0">
                        <flux:heading size="sm" class="truncate">{{ $person->display_name }}</flux:heading>
                        @if ($person->job_title)
                            <flux:text size="sm" class="truncate">{{ $person->job_title }}</flux:text>
                        @endif
                        @if ($person->organization_name)
                            <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $person->organization_name }}</flux:text>
                        @endif
                    </div>
                </flux:card>
            </a>
        @empty
            <flux:callout icon="magnifying-glass" class="sm:col-span-2 lg:col-span-3">
                {{ __('No one matches your search.') }}
            </flux:callout>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $people->links() }}
    </div>
</div>
