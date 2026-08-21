<?php

use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mosi')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{myGroups: \Illuminate\Support\Collection<int, Group>, groups: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function with(): array
    {
        $user = Auth::user();

        return [
            'myGroups' => $user->groups()->orderBy('id')->get(),
            'groups' => Group::query()
                ->visibleTo($user)
                ->when($this->search !== '', fn ($q) => $q->where('name->is', 'like', '%'.$this->search.'%')
                    ->orWhere('name->en', 'like', '%'.$this->search.'%'))
                ->withCount('members')
                ->orderBy('id')
                ->paginate(18),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('Groups') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search groups…')" class="max-w-xs" />
    </div>

    @if ($myGroups->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="sm">{{ __('My groups') }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($myGroups as $group)
                    <a wire:key="mine-{{ $group->id }}" href="{{ route('groups.show', $group) }}" wire:navigate
                        class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <flux:icon icon="user-group" class="text-primary-600" />
                        <span class="truncate font-medium">{{ $group->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-3">
        <flux:heading size="sm">{{ __('All groups') }}</flux:heading>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($groups as $group)
                <a wire:key="group-{{ $group->id }}" href="{{ route('groups.show', $group) }}" wire:navigate
                    class="block rounded-lg border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="user-group" variant="mini" class="text-zinc-400" />
                        <span class="truncate font-medium">{{ $group->name }}</span>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <flux:badge size="sm">{{ $group->type->label() }}</flux:badge>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ __(':count members', ['count' => $group->members_count]) }}
                        </flux:text>
                    </div>
                </a>
            @empty
                <flux:callout icon="user-group" class="sm:col-span-2 lg:col-span-3">{{ __('No groups found.') }}</flux:callout>
            @endforelse
        </div>

        <div>{{ $groups->links() }}</div>
    </div>
</div>
