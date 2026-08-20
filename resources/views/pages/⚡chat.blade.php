<?php

use App\Actions\Chat\MarkConversationRead;
use App\Actions\Chat\SendMessage;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Chat')] class extends Component {
    #[Url]
    public ?int $conversation = null;

    public string $body = '';

    public function mount(): void
    {
        if ($this->conversation === null) {
            $this->conversation = Conversation::query()->forUser(Auth::user())->value('id');
        }

        $this->openActive();
    }

    public function select(int $id): void
    {
        $this->conversation = $id;
        $this->openActive();
    }

    public function send(SendMessage $sendMessage): void
    {
        $active = $this->active();

        if ($active === null || trim($this->body) === '') {
            return;
        }

        $sendMessage(Auth::user(), $active, $this->body);
        $this->body = '';
    }

    private function openActive(): void
    {
        $active = $this->active();

        if ($active !== null) {
            app(MarkConversationRead::class)(Auth::user(), $active);
        } else {
            $this->conversation = null;
        }
    }

    private function active(): ?Conversation
    {
        if ($this->conversation === null) {
            return null;
        }

        $conversation = Conversation::query()->with('participants')->find($this->conversation);

        return $conversation !== null && $conversation->hasParticipant(Auth::user()) ? $conversation : null;
    }

    /**
     * @return array{me: \App\Models\User, conversations: \Illuminate\Support\Collection<int, Conversation>, active: ?Conversation, messages: \Illuminate\Support\Collection<int, \App\Models\Message>}
     */
    public function with(): array
    {
        $me = Auth::user();
        $active = $this->active();

        return [
            'me' => $me,
            'conversations' => Conversation::query()->forUser($me)->with('participants')->get(),
            'active' => $active,
            'messages' => $active === null
                ? collect()
                : $active->messages()->with('author')->latest()->limit(50)->get()->reverse()->values(),
        ];
    }
}; ?>

<div class="mx-auto flex h-[calc(100vh-8rem)] w-full max-w-5xl gap-4">
    {{-- Conversation list --}}
    <div class="hidden w-72 shrink-0 overflow-y-auto sm:block">
        <flux:heading size="lg" class="mb-3">{{ __('Chat') }}</flux:heading>
        <div class="space-y-1">
            @forelse ($conversations as $conversation)
                @php($unread = $conversation->participants->firstWhere('id', $me->id)?->pivot->unread_count ?? 0)
                <button type="button" wire:key="conv-{{ $conversation->id }}" wire:click="select({{ $conversation->id }})"
                        @class([
                            'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-start hover:bg-zinc-100 dark:hover:bg-zinc-800',
                            'bg-zinc-100 dark:bg-zinc-800' => $active?->id === $conversation->id,
                        ])>
                    <flux:avatar :name="$conversation->titleFor($me)" size="sm" />
                    <div class="min-w-0 flex-1">
                        <flux:text class="truncate font-medium">{{ $conversation->titleFor($me) }}</flux:text>
                    </div>
                    @if ($unread > 0)
                        <flux:badge size="sm" color="red">{{ $unread }}</flux:badge>
                    @endif
                </button>
            @empty
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No conversations yet.') }}</flux:text>
            @endforelse
        </div>
    </div>

    {{-- Thread --}}
    <div class="flex min-w-0 flex-1 flex-col rounded-xl border border-zinc-200 dark:border-zinc-700">
        @if ($active)
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="sm">{{ $active->titleFor($me) }}</flux:heading>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto p-4" wire:poll.5s>
                @foreach ($messages as $message)
                    @php($mine = $message->user_id === $me->id)
                    <div wire:key="msg-{{ $message->id }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div @class([
                            'max-w-[75%] rounded-2xl px-3 py-2',
                            'bg-primary-600 text-white' => $mine,
                            'bg-zinc-100 dark:bg-zinc-800' => ! $mine,
                        ])>
                            @unless ($mine)
                                <flux:text size="xs" class="font-medium opacity-70">{{ $message->author?->display_name }}</flux:text>
                            @endunless
                            <div class="whitespace-pre-wrap break-words text-sm">{{ $message->body }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <form wire:submit="send" class="flex items-center gap-2 border-t border-zinc-200 p-3 dark:border-zinc-700">
                <flux:input wire:model="body" :placeholder="__('Write a message…')" class="flex-1" autocomplete="off" />
                <flux:button type="submit" variant="primary" icon="paper-airplane" :aria-label="__('Send')" />
            </form>
        @else
            <div class="flex flex-1 items-center justify-center">
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Select a conversation to start chatting.') }}</flux:text>
            </div>
        @endif
    </div>
</div>
