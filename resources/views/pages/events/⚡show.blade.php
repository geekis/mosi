<?php

use App\Actions\Events\RespondToEvent;
use App\Actions\Events\UpdateEventCover;
use App\Enums\RsvpStatus;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Event')] class extends Component {
    use WithFileUploads;

    public Event $event;

    public ?TemporaryUploadedFile $coverFile = null;

    public function mount(Event $event): void
    {
        abort_unless(Auth::user()->can('view', $event), 404);

        // Eager-load media so coverUrl() does not trip the lazy-loading guard.
        $event->load('media');

        $this->event = $event;
    }

    public function respond(?string $status, RespondToEvent $respondToEvent): void
    {
        $respondToEvent(Auth::user(), $this->event, $status === null ? null : RsvpStatus::tryFrom($status));
    }

    public function saveCover(UpdateEventCover $updateEventCover): void
    {
        $this->authorize('update', $this->event);

        $this->validate(['coverFile' => ['required', 'image', 'max:8192']]);

        $updateEventCover($this->event, $this->coverFile);

        $this->coverFile = null;
        $this->event->load('media');
        $this->dispatch('modal-close', name: 'edit-cover');
        $this->dispatch('toast', variant: 'success', text: __('Cover updated.'));
    }

    /**
     * @return array{mine: ?RsvpStatus, canManage: bool, attendees: \Illuminate\Support\Collection<int, \App\Models\User>}
     */
    public function with(): array
    {
        return [
            'mine' => $this->event->rsvpFor(Auth::user()),
            'canManage' => Auth::user()->can('update', $this->event),
            'attendees' => $this->event->attendees()->wherePivot('status', RsvpStatus::Going->value)->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl space-y-4">
    {{-- Event header: cover as a banner with details below, wrapped in a card so it
         reads clearly against the page background. --}}
    <x-ui.card class="overflow-hidden p-0">
        @if ($event->coverUrl())
            <img src="{{ $event->coverUrl() }}" alt="" class="max-h-[360px] w-full object-cover" />
        @endif

        <div class="px-6 pb-6 pt-5">
            <div class="flex items-start justify-between gap-3">
                <x-ui.heading size="xl">{{ $event->title }}</x-ui.heading>
                @if ($canManage)
                    <x-ui.modal.trigger name="edit-cover">
                        <x-ui.button size="sm" variant="ghost" icon="photo">{{ __('Change cover') }}</x-ui.button>
                    </x-ui.modal.trigger>
                @endif
            </div>

            <x-ui.text class="mt-2">
                {{ $event->starts_at->translatedFormat('l j. F Y, H:i') }}
                @if ($event->location) · {{ $event->location }} @endif
                @if ($event->group) · {{ $event->group->name }} @endif
            </x-ui.text>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <div class="inline-flex gap-1">
                    <x-ui.button size="sm" :variant="$mine === RsvpStatus::Going ? 'primary' : 'ghost'" wire:click="respond('going')">{{ __('Going') }}</x-ui.button>
                    <x-ui.button size="sm" :variant="$mine === RsvpStatus::Maybe ? 'primary' : 'ghost'" wire:click="respond('maybe')">{{ __('Maybe') }}</x-ui.button>
                    <x-ui.button size="sm" :variant="$mine === RsvpStatus::Declined ? 'primary' : 'ghost'" wire:click="respond('declined')">{{ __('Can’t go') }}</x-ui.button>
                </div>

                <x-ui.button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('events.ics', $event)" :navigate="false">
                    {{ __('Add to calendar') }}
                </x-ui.button>
            </div>

            @if ($event->description)
                <div class="prose prose-sm mt-6 max-w-none whitespace-pre-line">{{ $event->description }}</div>
            @endif
        </div>
    </x-ui.card>

    {{-- Attendees --}}
    <x-ui.card>
        <x-ui.heading size="sm">{{ __('Going') }} ({{ $attendees->count() }})</x-ui.heading>
        <div class="mt-3 flex flex-wrap gap-3">
            @forelse ($attendees as $person)
                <div wire:key="attendee-{{ $person->id }}" class="flex items-center gap-2">
                    <x-avatar :user="$person" size="xs" />
                    <x-ui.text size="sm">{{ $person->display_name }}</x-ui.text>
                </div>
            @empty
                <x-ui.text size="sm">{{ __('No one has responded yet.') }}</x-ui.text>
            @endforelse
        </div>
    </x-ui.card>

    @if ($canManage)
        <x-ui.modal name="edit-cover" class="max-w-lg">
            <div class="space-y-4">
                <x-ui.heading size="lg">{{ __('Change cover') }}</x-ui.heading>
                <x-ui.input type="file" wire:model="coverFile" :label="__('Cover image')" accept="image/*" />
                @if ($coverFile && $coverFile->isPreviewable())
                    <img src="{{ $coverFile->temporaryUrl() }}" alt="" class="h-40 w-full rounded-lg object-cover" />
                @endif
                <div class="flex justify-end">
                    <x-ui.button variant="primary" wire:click="saveCover" data-test="save-event-cover">{{ __('Save') }}</x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
