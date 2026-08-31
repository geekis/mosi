<?php

use App\Actions\Events\CreateEvent;
use App\Actions\Events\RespondToEvent;
use App\Actions\Events\UpdateEventCover;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Group $group;

    public string $title = '';

    public string $location = '';

    public string $starts_at = '';

    public ?TemporaryUploadedFile $cover = null;

    public function create(CreateEvent $createEvent, UpdateEventCover $updateEventCover): void
    {
        abort_unless($this->group->hasMember(Auth::user()), 403);

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
            'group_id' => $this->group->id,
        ]);

        if ($this->cover !== null) {
            $updateEventCover($event, $this->cover);
        }

        $this->reset('title', 'location', 'starts_at', 'cover');
        $this->dispatch('modal-close', name: 'create-group-event');
        $this->dispatch('toast', variant: 'success', text: __('Event created.'));
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
     * @return array{upcoming: \Illuminate\Support\Collection<int, Event>, past: \Illuminate\Support\Collection<int, Event>}
     */
    public function with(): array
    {
        $base = fn () => Event::query()
            ->where('group_id', $this->group->id)
            ->visibleTo(Auth::user())
            ->with(['attendees', 'media'])
            ->withCount('attendees');

        return [
            'upcoming' => $base()->upcoming()->get(),
            'past' => $base()->where('starts_at', '<', now()->startOfDay())->latest('starts_at')->limit(10)->get(),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <x-ui.heading size="lg">{{ __('Events') }}</x-ui.heading>
        <x-ui.modal.trigger name="create-group-event">
            <x-ui.button size="sm" variant="primary" icon="plus">{{ __('Create event') }}</x-ui.button>
        </x-ui.modal.trigger>
    </div>

    @php($me = auth()->user())

    @forelse ($upcoming as $event)
        <x-ui.card wire:key="event-{{ $event->id }}">
            @php($mine = $event->rsvpFor($me))
            @if ($event->coverUrl())
                <a href="{{ route('events.show', $event) }}" wire:navigate>
                    <img src="{{ $event->coverUrl() }}" alt="" class="mb-4 h-40 w-full rounded-lg object-cover" />
                </a>
            @endif
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <a href="{{ route('events.show', $event) }}" class="hover:underline" wire:navigate>
                        <x-ui.heading size="lg">{{ $event->title }}</x-ui.heading>
                    </a>
                    <x-ui.text size="sm" class="mt-1">
                        {{ $event->starts_at->translatedFormat('j. M Y, H:i') }}
                        @if ($event->location) · {{ $event->location }} @endif
                    </x-ui.text>
                    <x-ui.text size="sm" class="mt-1">
                        {{ __(':count attending', ['count' => $event->attendees_count]) }}
                    </x-ui.text>
                </div>

                <div class="inline-flex gap-1">
                    <x-ui.button size="sm" :variant="$mine === RsvpStatus::Going ? 'primary' : 'filled'" wire:click="respond({{ $event->id }}, 'going')">{{ __('Going') }}</x-ui.button>
                    <x-ui.button size="sm" :variant="$mine === RsvpStatus::Maybe ? 'primary' : 'filled'" wire:click="respond({{ $event->id }}, 'maybe')">{{ __('Maybe') }}</x-ui.button>
                    <x-ui.button size="sm" :variant="$mine === RsvpStatus::Declined ? 'primary' : 'filled'" wire:click="respond({{ $event->id }}, 'declined')">{{ __('Can’t go') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @empty
        <x-ui.alert icon="calendar-days">{{ __('No upcoming events yet.') }}</x-ui.alert>
    @endforelse

    @if ($past->isNotEmpty())
        <x-ui.heading size="sm" class="pt-2 text-mist-500 dark:text-zinc-400">{{ __('Past events') }}</x-ui.heading>
        @foreach ($past as $event)
            <x-ui.card wire:key="past-event-{{ $event->id }}" class="opacity-75">
                <a href="{{ route('events.show', $event) }}" class="hover:underline" wire:navigate>
                    <x-ui.heading>{{ $event->title }}</x-ui.heading>
                </a>
                <x-ui.text size="sm" class="mt-1">
                    {{ $event->starts_at->translatedFormat('j. M Y, H:i') }}
                    @if ($event->location) · {{ $event->location }} @endif
                </x-ui.text>
            </x-ui.card>
        @endforeach
    @endif

    {{-- Create event modal --}}
    <x-ui.modal name="create-group-event" class="max-w-lg">
        <form wire:submit="create" class="space-y-4">
            <x-ui.heading size="lg">{{ __('Create event') }}</x-ui.heading>
            <x-ui.input wire:model="title" :label="__('Title')" required />
            <x-ui.input wire:model="location" :label="__('Location')" />
            <x-ui.input type="datetime-local" wire:model="starts_at" :label="__('Starts at')" required />

            <div>
                <x-ui.input type="file" wire:model="cover" :label="__('Cover image')" accept="image/*" />
                @if ($cover && $cover->isPreviewable())
                    <img src="{{ $cover->temporaryUrl() }}" alt="" class="mt-2 h-32 w-full rounded-lg object-cover" />
                @endif
            </div>

            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary">{{ __('Create event') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
