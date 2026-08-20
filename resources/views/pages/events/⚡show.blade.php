<?php

use App\Actions\Events\RespondToEvent;
use App\Enums\RsvpStatus;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Event')] class extends Component {
    public Event $event;

    public function mount(Event $event): void
    {
        abort_unless(Auth::user()->can('view', $event), 404);

        $this->event = $event;
    }

    public function respond(?string $status, RespondToEvent $respondToEvent): void
    {
        $respondToEvent(Auth::user(), $this->event, $status === null ? null : RsvpStatus::tryFrom($status));
    }

    /**
     * @return array{mine: ?RsvpStatus, attendees: \Illuminate\Support\Collection<int, \App\Models\User>}
     */
    public function with(): array
    {
        return [
            'mine' => $this->event->rsvpFor(Auth::user()),
            'attendees' => $this->event->attendees()->wherePivot('status', RsvpStatus::Going->value)->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ $event->title }}</flux:heading>

    <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
        {{ $event->starts_at->translatedFormat('l j. F Y, H:i') }}
        @if ($event->location) · {{ $event->location }} @endif
        @if ($event->group) · {{ $event->group->name }} @endif
    </flux:text>

    <div class="mt-4">
        <flux:button.group>
            <flux:button size="sm" :variant="$mine === RsvpStatus::Going ? 'primary' : 'ghost'" wire:click="respond('going')">{{ __('Going') }}</flux:button>
            <flux:button size="sm" :variant="$mine === RsvpStatus::Maybe ? 'primary' : 'ghost'" wire:click="respond('maybe')">{{ __('Maybe') }}</flux:button>
            <flux:button size="sm" :variant="$mine === RsvpStatus::Declined ? 'primary' : 'ghost'" wire:click="respond('declined')">{{ __('Can’t go') }}</flux:button>
        </flux:button.group>

        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" href="{{ route('events.ics', $event) }}" class="ms-2">
            {{ __('Add to calendar') }}
        </flux:button>
    </div>

    @if ($event->description)
        <div class="prose prose-sm mt-6 max-w-none whitespace-pre-line dark:prose-invert">{{ $event->description }}</div>
    @endif

    <flux:separator class="my-6" variant="subtle" />

    <flux:heading size="sm">{{ __('Going') }} ({{ $attendees->count() }})</flux:heading>
    <div class="mt-3 flex flex-wrap gap-3">
        @forelse ($attendees as $person)
            <div wire:key="attendee-{{ $person->id }}" class="flex items-center gap-2">
                <flux:avatar :name="$person->display_name" :initials="$person->initials()" :src="$person->avatarUrl()" size="xs" />
                <flux:text size="sm">{{ $person->display_name }}</flux:text>
            </div>
        @empty
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No one has responded yet.') }}</flux:text>
        @endforelse
    </div>
</div>
