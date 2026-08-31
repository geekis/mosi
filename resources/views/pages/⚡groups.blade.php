<?php

use App\Actions\Groups\AcceptGroupInvitation;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\DeclineGroupInvitation;
use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\GroupInvitation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mosi')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $name_is = '';

    public string $name_en = '';

    public string $description_is = '';

    public string $description_en = '';

    public string $type = 'interest';

    public string $visibility = 'open';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createGroup(CreateGroup $createGroup): void
    {
        $this->authorize('create', Group::class);

        $validated = $this->validate([
            'name_is' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_is' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:interest,project'],
            'visibility' => ['required', 'in:open,closed,secret'],
        ]);

        $group = $createGroup(Auth::user(), [
            'name' => ['is' => $validated['name_is'], 'en' => $validated['name_en']],
            'description' => ['is' => $validated['description_is'] ?? '', 'en' => $validated['description_en'] ?? ''],
            'type' => GroupType::from($validated['type']),
            'visibility' => GroupVisibility::from($validated['visibility']),
        ]);

        $this->dispatch('modal-close', name: 'create-group');
        $this->dispatch('toast', text: __('Group created.'), variant: 'success');

        $this->redirect(route('groups.show', $group), navigate: true);
    }

    public function acceptInvitation(int $invitationId, AcceptGroupInvitation $accept): void
    {
        $invitation = $this->myInvitation($invitationId);

        if ($invitation !== null) {
            $group = $invitation->group;
            $accept($invitation);
            $this->dispatch('toast', text: __('You joined :group.', ['group' => $group->name]), variant: 'success');
        }
    }

    public function declineInvitation(int $invitationId, DeclineGroupInvitation $decline): void
    {
        $invitation = $this->myInvitation($invitationId);

        if ($invitation !== null) {
            $decline($invitation);
        }
    }

    /**
     * An invitation may only be answered by its own invitee — the id comes from the
     * client, so scope the lookup to the signed-in user.
     */
    private function myInvitation(int $invitationId): ?GroupInvitation
    {
        return GroupInvitation::query()
            ->whereKey($invitationId)
            ->where('user_id', Auth::id())
            ->first();
    }

    /**
     * @return array{invitations: \Illuminate\Support\Collection<int, GroupInvitation>, myGroups: \Illuminate\Support\Collection<int, Group>, groups: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function with(): array
    {
        $user = Auth::user();

        return [
            'invitations' => GroupInvitation::query()
                ->where('user_id', $user->id)
                ->with(['group.media', 'inviter'])
                ->get(),
            'myGroups' => $user->groups()->with('media')->orderBy('id')->get(),
            'groups' => Group::query()
                ->visibleTo($user)
                ->when($this->search !== '', fn ($q) => $q->where('name->is', 'like', '%'.$this->search.'%')
                    ->orWhere('name->en', 'like', '%'.$this->search.'%'))
                ->with('media')
                ->withCount('members')
                ->orderBy('id')
                ->paginate(18),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-ui.heading size="xl">{{ __('Groups') }}</x-ui.heading>
        <div class="flex items-center gap-2">
            <x-ui.input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search groups…')" class="max-w-xs" />
            <x-ui.modal.trigger name="create-group">
                <x-ui.button variant="primary" icon="plus">{{ __('New group') }}</x-ui.button>
            </x-ui.modal.trigger>
        </div>
    </div>

    @if ($invitations->isNotEmpty())
        <div class="space-y-3">
            <x-ui.heading size="sm">{{ __('Invitations') }}</x-ui.heading>
            <div class="space-y-2">
                @foreach ($invitations as $invitation)
                    <x-ui.card wire:key="invite-{{ $invitation->id }}" size="sm" class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <x-ui.text class="font-medium text-ink-900 dark:text-white">{{ $invitation->group->name }}</x-ui.text>
                            <x-ui.text size="sm">
                                {{ __(':name invited you', ['name' => $invitation->inviter->display_name]) }}
                            </x-ui.text>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.button size="sm" variant="primary" wire:click="acceptInvitation({{ $invitation->id }})">
                                {{ __('Accept') }}
                            </x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="declineInvitation({{ $invitation->id }})">
                                {{ __('Decline') }}
                            </x-ui.button>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </div>
    @endif

    @if ($myGroups->isNotEmpty())
        <div class="space-y-3">
            <x-ui.heading size="sm">{{ __('My groups') }}</x-ui.heading>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($myGroups as $group)
                    <a wire:key="mine-{{ $group->id }}" href="{{ route('groups.show', $group) }}" wire:navigate>
                        <x-ui.card size="sm" class="flex items-center gap-3">
                            @if ($group->avatarUrl())
                                <img src="{{ $group->avatarUrl() }}" alt="" class="size-8 shrink-0 rounded-lg object-cover" />
                            @else
                                <x-ui.icon name="user-group" class="text-brand-600" />
                            @endif
                            <span class="truncate font-medium">{{ $group->name }}</span>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-3">
        <x-ui.heading size="sm">{{ __('All groups') }}</x-ui.heading>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($groups as $group)
                <a wire:key="group-{{ $group->id }}" href="{{ route('groups.show', $group) }}" wire:navigate>
                    <x-ui.card size="sm" class="overflow-hidden p-0">
                        <x-group-banner :group="$group" />
                        <div class="p-4">
                            <div class="flex items-center gap-2">
                                @if ($group->avatarUrl())
                                    <img src="{{ $group->avatarUrl() }}" alt="" class="size-8 shrink-0 rounded-lg object-cover" />
                                @else
                                    <x-ui.icon name="user-group" variant="mini" class="text-mist-400" />
                                @endif
                                <span class="truncate font-medium">{{ $group->name }}</span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <x-ui.badge size="sm">{{ $group->type->label() }}</x-ui.badge>
                                <x-ui.text size="sm">
                                    {{ __(':count members', ['count' => $group->members_count]) }}
                                </x-ui.text>
                            </div>
                        </div>
                    </x-ui.card>
                </a>
            @empty
                <x-ui.alert icon="user-group" class="sm:col-span-2 lg:col-span-3">{{ __('No groups found.') }}</x-ui.alert>
            @endforelse
        </div>

        <div>{{ $groups->links() }}</div>
    </div>

    {{-- Create group modal --}}
    <x-ui.modal name="create-group" class="max-w-lg">
        <div class="space-y-4">
            <x-ui.heading size="lg">{{ __('New group') }}</x-ui.heading>

            <x-ui.input wire:model="name_is" :label="__('Name (Icelandic)')" />
            <x-ui.input wire:model="name_en" :label="__('Name (English)')" />
            <x-ui.textarea wire:model="description_is" :label="__('Description (Icelandic)')" rows="2" />
            <x-ui.textarea wire:model="description_en" :label="__('Description (English)')" rows="2" />

            <x-ui.select wire:model="type" :label="__('Type')">
                <x-ui.select.option value="interest">{{ App\Enums\GroupType::Interest->label() }}</x-ui.select.option>
                <x-ui.select.option value="project">{{ App\Enums\GroupType::Project->label() }}</x-ui.select.option>
            </x-ui.select>

            <x-ui.select wire:model="visibility" :label="__('Visibility')">
                <x-ui.select.option value="open">{{ App\Enums\GroupVisibility::Open->label() }}</x-ui.select.option>
                <x-ui.select.option value="closed">{{ App\Enums\GroupVisibility::Closed->label() }}</x-ui.select.option>
                <x-ui.select.option value="secret">{{ App\Enums\GroupVisibility::Secret->label() }}</x-ui.select.option>
            </x-ui.select>

            <div class="flex justify-end">
                <x-ui.button variant="primary" wire:click="createGroup" data-test="create-group-button">
                    {{ __('Create group') }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
