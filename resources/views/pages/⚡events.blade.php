<?php

use App\Actions\Events\CreateEvent;
use App\Actions\Events\RespondToEvent;
use App\Actions\Events\UpdateEventCover;
use App\Enums\RsvpStatus;
use App\Models\Event;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Events')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public string $location = '';

    public string $starts_at = '';

    public ?TemporaryUploadedFile $cover = null;

    public function create(CreateEvent $createEvent, UpdateEventCover $updateEventCover): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'cover' => ['nullable', 'image', 'max:8192'],
        ]);

        $event = $createEvent(Auth::user(), [
            'title' => ['is' => $validated['title'], 'en' => $validated['title']],
            'location' => $validated['location'] ?: null,
            'starts_at' => $validated['starts_at'],
        ]);

        if ($this->cover !== null) {
            $updateEventCover($event, $this->cover);
        }

        $this->reset('title', 'location', 'starts_at', 'cover');
        Flux::modal('create-event')->close();
        Flux::toast(variant: 'success', text: __('Event created.'));
    }

    public function respond(int $eventId, ?string $status, RespondToEvent $respondToEvent): void
    {
        $event = Event::query()->find($eventId);

        if ($event === null || ! Auth::user()->can('view', $event)) {
            return;
        }

        $respondToEvent(Auth::user(), $event, $status === null ? null : RsvpStatus::tryFrom($status));
    }

    /**
     * @return array{events: \Illuminate\Support\Collection<int, Event>}
     */
    public function with(): array
    {
        return [
            'events' => Event::query()
                ->visibleTo(Auth::user())
                ->upcoming()
                ->with(['group', 'attendees', 'media'])
                ->withCount('attendees')
                ->limit(50)
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Events') }}</flux:heading>

        <flux:modal.trigger name="create-event">
            <flux:button size="sm" variant="primary" icon="plus">{{ __('New event') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($events as $event)
            @php($mine = $event->rsvpFor($me = auth()->user()))
            <flux:card wire:key="event-{{ $event->id }}">
                @if ($event->coverUrl())
                    <a href="{{ route('events.show', $event) }}" wire:navigate>
                        <img src="{{ $event->coverUrl() }}" alt="" class="mb-4 h-40 w-full rounded-lg object-cover" />
                    </a>
                @endif
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <a href="{{ route('events.show', $event) }}" class="hover:underline" wire:navigate>
                            <flux:heading size="lg">{{ $event->title }}</flux:heading>
                        </a>
                        <flux:text size="sm" class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ $event->starts_at->translatedFormat('j. M Y, H:i') }}
                            @if ($event->location) · {{ $event->location }} @endif
                            @if ($event->group) · {{ $event->group->name }} @endif
                        </flux:text>
                        <flux:text size="sm" class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ $event->attendees_count }} {{ __('attending') }}
                        </flux:text>
                    </div>

                    <flux:button.group>
                        <flux:button size="sm" :variant="$mine === RsvpStatus::Going ? 'primary' : 'filled'" wire:click="respond({{ $event->id }}, 'going')">{{ __('Going') }}</flux:button>
                        <flux:button size="sm" :variant="$mine === RsvpStatus::Maybe ? 'primary' : 'filled'" wire:click="respond({{ $event->id }}, 'maybe')">{{ __('Maybe') }}</flux:button>
                        <flux:button size="sm" :variant="$mine === RsvpStatus::Declined ? 'primary' : 'filled'" wire:click="respond({{ $event->id }}, 'declined')">{{ __('Can’t go') }}</flux:button>
                    </flux:button.group>
                </div>
            </flux:card>
        @empty
            <flux:callout icon="calendar">{{ __('No upcoming events.') }}</flux:callout>
        @endforelse
    </div>

    {{-- Create event modal --}}
    <flux:modal name="create-event" class="w-full max-w-lg">
        <form wire:submit="create" class="space-y-4">
            <flux:heading size="lg">{{ __('New event') }}</flux:heading>
            <flux:input wire:model="title" :label="__('Title')" required />
            <flux:input wire:model="location" :label="__('Location')" />
            <flux:input type="datetime-local" wire:model="starts_at" :label="__('Starts at')" required />

            <div>
                <flux:input type="file" wire:model="cover" :label="__('Cover image')" accept="image/*" />
                <flux:error name="cover" />
                @if ($cover && $cover->isPreviewable())
                    <img src="{{ $cover->temporaryUrl() }}" alt="" class="mt-2 h-32 w-full rounded-lg object-cover" />
                @endif
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Create event') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
