<?php

use App\Actions\Chat\MarkConversationRead;
use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartDirectConversation;
use App\Actions\Mentions\NotifyMentions;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Chat')] class extends Component {
    use WithFileUploads;

    #[Url]
    public ?int $conversation = null;

    public string $body = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

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

    /**
     * Subscribe to the open thread's Reverb channel so incoming messages appear live.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        if ($this->conversation === null) {
            return [];
        }

        return ["echo-private:conversation.{$this->conversation},MessageSent" => 'onMessageReceived'];
    }

    /**
     * A message arrived over Reverb for the open thread: re-render to show it, mark the
     * thread read (the user is looking at it), and scroll to the newest message.
     */
    public function onMessageReceived(): void
    {
        $active = $this->active();

        if ($active !== null) {
            app(MarkConversationRead::class)(Auth::user(), $active);
            $this->dispatch('chat-read');
        }

        $this->dispatch('chat-scroll');
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    /**
     * Open (or reuse) a direct thread with a fellow participant of the current group chat.
     * Guarded on membership of the active thread — the id comes from the client, so it is
     * never trusted to be a real co-member.
     */
    public function startChatWith(int $userId, StartDirectConversation $startDirectConversation): void
    {
        $me = Auth::user();
        $active = $this->active();
        $user = User::find($userId);

        if ($user === null || $user->id === $me->id || $active === null || ! $active->hasParticipant($user)) {
            return;
        }

        $conversation = $startDirectConversation($me, $user);

        Flux::modal('members')->close();
        $this->select($conversation->id);
    }

    public function send(SendMessage $sendMessage): void
    {
        $active = $this->active();

        if ($active === null || (trim($this->body) === '' && $this->attachments === [])) {
            return;
        }

        $this->validate([
            'attachments' => ['array', 'max:10'],
            // 50 MB per file so short videos are usable — the PHP/Livewire upload limits
            // (upload_max_filesize, Livewire temporary-upload rules) must allow this too.
            'attachments.*' => ['file', 'max:51200'],
        ]);

        $sendMessage(Auth::user(), $active, $this->body, $this->attachments);

        $this->body = '';
        $this->attachments = [];
        $this->dispatch('chat-scroll');
    }

    private function openActive(): void
    {
        $active = $this->active();

        if ($active !== null) {
            app(MarkConversationRead::class)(Auth::user(), $active);
            // Let the sidebar unread badge refresh now that this thread is read.
            $this->dispatch('chat-read');
        } else {
            $this->conversation = null;
        }
    }

    private function active(): ?Conversation
    {
        if ($this->conversation === null) {
            return null;
        }

        $conversation = Conversation::query()->with('participants.media')->find($this->conversation);

        return $conversation !== null && $conversation->hasParticipant(Auth::user()) ? $conversation : null;
    }

    /**
     * The @mention data for the active thread: an autocomplete list of the other
     * participants (you cannot @mention yourself), plus a handle-keyed map so a written
     * @handle in a message body renders as a highlighted mention. Only people in the chat
     * appear, so a message can neither suggest nor resolve anyone outside the thread.
     *
     * @return array{list: list<array{handle: string, name: string}>, map: array<string, array{name: string, title: string, url: string|null}>}
     */
    private function mentionData(?Conversation $active, User $me): array
    {
        $list = [];
        $map = [];

        if ($active === null) {
            return ['list' => $list, 'map' => $map];
        }

        foreach ($active->participants as $participant) {
            $handle = NotifyMentions::handleFor($participant);

            if ($handle === '') {
                continue;
            }

            $map[mb_strtolower($handle)] = [
                'name' => (string) $participant->display_name,
                'title' => (string) ($participant->job_title ?? ''),
                'url' => $participant->external_id !== null ? route('people.show', $participant) : null,
            ];

            if ($participant->id !== $me->id) {
                $list[] = ['handle' => $handle, 'name' => (string) $participant->display_name];
            }
        }

        return ['list' => $list, 'map' => $map];
    }

    /**
     * @return array{me: \App\Models\User, conversations: \Illuminate\Support\Collection<int, Conversation>, active: ?Conversation, messages: \Illuminate\Support\Collection<int, Message>, lastMineId: ?int, seenBy: \Illuminate\Support\Collection<int, User>, mentionables: list<array{handle: string, name: string}>, mentionMap: array<string, array{name: string, title: string, url: string|null}>}
     */
    public function with(): array
    {
        $me = Auth::user();
        $active = $this->active();
        $mentions = $this->mentionData($active, $me);

        $messages = $active === null
            ? collect()
            : $active->messages()->with(['author.media', 'media'])->latest()->limit(50)->get()->reverse()->values();

        // A read receipt is derived from each participant's denormalised last_read_at
        // (.ai/rules/realtime.md) — no separate receipts table. We only annotate my most
        // recent message, which is where chat clients conventionally show "seen".
        $lastMine = $messages->last(fn (Message $message): bool => $message->user_id === $me->id);
        $seenBy = collect();

        if ($active !== null && $lastMine !== null) {
            $seenBy = $active->participants
                ->reject(fn (User $participant): bool => $participant->id === $me->id)
                ->filter(function (User $participant) use ($lastMine): bool {
                    $readAt = $participant->pivot->last_read_at;

                    return $readAt !== null && Carbon::parse($readAt)->gte($lastMine->created_at);
                })
                ->values();
        }

        return [
            'me' => $me,
            'conversations' => Conversation::query()->forUser($me)->with('participants.media')->get(),
            'active' => $active,
            'messages' => $messages,
            'lastMineId' => $lastMine?->id,
            'seenBy' => $seenBy,
            'mentionables' => $mentions['list'],
            'mentionMap' => $mentions['map'],
        ];
    }
}; ?>

<div class="mx-auto flex h-[calc(100vh-8rem)] w-full max-w-5xl gap-4"
    x-data="{
        viewer: null,
        open(kind, url, name) { this.viewer = { kind, url, name }; },
        close() { this.viewer = null; },
    }"
    x-on:keydown.escape.window="close()">
    {{-- Conversation list --}}
    <div class="hidden w-72 shrink-0 p-4 overflow-y-auto sm:block rounded-2xl bg-white">
        <flux:heading size="lg" class="mb-3">{{ __('Chat') }}</flux:heading>
        <div class="space-y-1">
            @forelse ($conversations as $conversation)
                @php($unread = $conversation->participants->firstWhere('id', $me->id)?->pivot->unread_count ?? 0)
                @php($listOther = $conversation->type->value === 'direct' ? $conversation->otherParticipant($me) : null)
                <button type="button" wire:key="conv-{{ $conversation->id }}" wire:click="select({{ $conversation->id }})"
                        @class([
                            'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-start hover:bg-zinc-100 dark:hover:bg-zinc-800',
                            'bg-zinc-100 dark:bg-zinc-800' => $active?->id === $conversation->id,
                        ])>
                    <flux:avatar :name="$conversation->titleFor($me)" :src="$listOther?->avatarUrl()"
                        :icon="$conversation->type->value === 'group' ? 'user-group' : null" size="sm"
                        :badge="$listOther?->isOnline() ?? false" badge:color="green" badge:circle badge:position="bottom right" />
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
    <div class="flex min-w-0 flex-1 flex-col rounded-xl bg-white border border-zinc-200 dark:border-zinc-700">
        @if ($active)
            @php($otherParticipant = $active->type->value === 'direct' ? $active->otherParticipant($me) : null)
            <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <div class="min-w-0">
                    <flux:heading size="sm" class="truncate">{{ $active->titleFor($me) }}</flux:heading>
                    @if ($otherParticipant)
                        <x-user-presence :user="$otherParticipant" class="mt-0.5" />
                    @endif
                </div>
                @if ($active->type->value === 'group')
                    <flux:modal.trigger name="members">
                        <flux:button size="sm" variant="ghost" icon="users">{{ __('Members') }}</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>

            <div class="flex flex-1 flex-col overflow-y-auto p-4" wire:poll.15s
                x-data x-init="$el.scrollTop = $el.scrollHeight"
                x-on:chat-scroll.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                {{-- mt-auto pins the thread to the bottom so early messages start at the floor, not the ceiling. --}}
                <div class="mt-auto space-y-3">
                    @foreach ($messages as $message)
                        @php($mine = $message->user_id === $me->id)
                        @php($images = $message->getMedia('images'))
                        @php($videos = $message->getMedia('videos'))
                        @php($files = $message->getMedia('attachments'))
                        <div wire:key="msg-{{ $message->id }}" class="flex gap-2 {{ $mine ? 'justify-end' : 'justify-start' }}">
                            @unless ($mine)
                                <flux:avatar :name="$message->author?->display_name" :src="$message->author?->avatarUrl()" size="xs" class="mt-4 shrink-0" />
                            @endunless

                            <div class="flex max-w-[75%] flex-col gap-1 {{ $mine ? 'items-end' : 'items-start' }}">
                                @unless ($mine)
                                    <span class="mb-0.5 px-1 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ $message->author?->display_name }}</span>
                                @endunless

                                @foreach ($images as $image)
                                    <button type="button" wire:key="img-{{ $image->id }}"
                                        x-on:click="open('image', @js($image->getUrl()), @js($image->name))"
                                        class="cursor-zoom-in">
                                        <img src="{{ $image->getUrl() }}" alt="{{ $image->name }}" class="max-h-64 max-w-full rounded-2xl object-cover" />
                                    </button>
                                @endforeach

                                @foreach ($videos as $video)
                                    <button type="button" wire:key="video-{{ $video->id }}"
                                        x-on:click="open('video', @js($video->getUrl()), @js($video->name))"
                                        class="group relative cursor-zoom-in overflow-hidden rounded-2xl">
                                        <video src="{{ $video->getUrl() }}#t=0.1" preload="metadata" class="max-h-64 max-w-full object-cover" muted playsinline></video>
                                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/25 transition group-hover:bg-black/35">
                                            <flux:icon icon="play-circle" class="size-12 text-white/90" />
                                        </span>
                                    </button>
                                @endforeach

                                @if (trim($message->body) !== '' || $files->isNotEmpty())
                                    <div @class([
                                        'rounded-2xl px-3 py-2',
                                        'bg-primary-600 text-white' => $mine,
                                        'bg-zinc-100 dark:bg-zinc-800' => ! $mine,
                                    ])>
                                        @if (trim($message->body) !== '')
                                            <x-post-body :body="$message->body" :mentions="$mentionMap" variant="plain" class="break-words text-sm" />
                                        @endif

                                        @foreach ($files as $file)
                                            <a wire:key="file-{{ $file->id }}" href="{{ $file->getUrl() }}" target="_blank" rel="noopener"
                                                @class([
                                                    'mt-1 flex items-center gap-2 text-sm underline underline-offset-2',
                                                    'text-white' => $mine,
                                                ])>
                                                <flux:icon icon="paper-clip" variant="micro" />
                                                <span class="truncate">{{ $file->name }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($message->id === $lastMineId && $seenBy->isNotEmpty())
                                    <span class="px-1 text-[11px] text-zinc-400 dark:text-zinc-500">
                                        @if ($active->type->value === 'group')
                                            {{ __('Seen by :count', ['count' => $seenBy->count()]) }}
                                        @else
                                            {{ __('Seen') }}
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <form wire:submit="send" class="border-t border-zinc-200 p-3 dark:border-zinc-700">
                @if ($attachments)
                    <div class="mb-2 flex flex-wrap gap-2">
                        @foreach ($attachments as $index => $file)
                            <div wire:key="pending-{{ $index }}" class="flex items-center gap-2 rounded-lg bg-zinc-100 py-1 pe-1 ps-2 text-xs dark:bg-zinc-800">
                                @if ($file->isPreviewable() && str_starts_with($file->getMimeType(), 'image/'))
                                    <img src="{{ $file->temporaryUrl() }}" alt="" class="h-8 w-8 rounded object-cover" />
                                @elseif (str_starts_with((string) $file->getMimeType(), 'video/'))
                                    <flux:icon icon="film" variant="micro" class="text-zinc-400" />
                                @else
                                    <flux:icon icon="paper-clip" variant="micro" class="text-zinc-400" />
                                @endif
                                <span class="max-w-[10rem] truncate">{{ $file->getClientOriginalName() }}</span>
                                <flux:button type="button" size="xs" variant="ghost" icon="x-mark"
                                    wire:click="removeAttachment({{ $index }})" :aria-label="__('Remove')" />
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center gap-2">
                    <input type="file" multiple wire:model="attachments" x-ref="files" class="hidden" />
                    <flux:button type="button" variant="ghost" icon="paper-clip" :aria-label="__('Attach files')"
                        x-on:click="$refs.files.click()" />
                    <div x-data="mentionBox(@js(array_values($mentionables)))" class="relative flex-1">
                        <flux:input wire:model="body" :placeholder="__('Write a message…')" class="w-full" autocomplete="off" />
                        <x-mention-suggestions placement="top" />
                    </div>
                    <flux:button type="submit" variant="primary" icon="paper-airplane" :aria-label="__('Send')" />
                </div>

                <flux:error name="attachments" />
                <flux:error name="attachments.*" />
            </form>

            @if ($active->type->value === 'group')
                <flux:modal name="members" class="w-full max-w-md">
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Members') }}</flux:heading>
                        <div class="max-h-96 space-y-1 overflow-y-auto">
                            @foreach ($active->participants as $participant)
                                <div wire:key="member-{{ $participant->id }}" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                    <flux:avatar :name="$participant->display_name" :src="$participant->avatarUrl()" size="sm"
                                        :badge="$participant->isOnline()" badge:color="green" badge:circle badge:position="bottom right" />
                                    <div class="min-w-0 flex-1">
                                        @if ($participant->external_id)
                                            <a href="{{ route('people.show', $participant) }}" wire:navigate class="hover:underline">
                                                <flux:text class="truncate font-medium">{{ $participant->display_name }}</flux:text>
                                            </a>
                                        @else
                                            <flux:text class="truncate font-medium">{{ $participant->display_name }}</flux:text>
                                        @endif
                                        @if ($participant->job_title)
                                            <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $participant->job_title }}</flux:text>
                                        @endif
                                    </div>
                                    @if ($participant->id === $me->id)
                                        <flux:badge size="sm">{{ __('You') }}</flux:badge>
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="chat-bubble-oval-left"
                                            wire:click="startChatWith({{ $participant->id }})">{{ __('Message') }}</flux:button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </flux:modal>
            @endif
        @else
            <div class="flex flex-1 items-center justify-center">
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Select a conversation to start chatting.') }}</flux:text>
            </div>
        @endif
    </div>

    {{-- Fullscreen media viewer: opened from any image/video bubble, click-outside or Esc closes. --}}
    <template x-if="viewer">
        <div class="fixed inset-0 z-50 flex flex-col bg-black/90" x-on:click="close()">
            <div class="flex items-center justify-end gap-2 p-4">
                <a :href="viewer.url" :download="viewer.name" x-on:click.stop
                    class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/20">
                    <flux:icon icon="arrow-down-tray" variant="micro" />
                    {{ __('Download') }}
                </a>
                <button type="button" x-on:click="close()" :aria-label="@js(__('Close'))"
                    class="inline-flex items-center rounded-lg bg-white/10 p-2 text-white hover:bg-white/20">
                    <flux:icon icon="x-mark" variant="micro" />
                </button>
            </div>
            <div class="flex min-h-0 flex-1 items-center justify-center p-4 pt-0">
                <template x-if="viewer.kind === 'image'">
                    <img :src="viewer.url" :alt="viewer.name" x-on:click.stop class="max-h-full max-w-full rounded-lg object-contain" />
                </template>
                <template x-if="viewer.kind === 'video'">
                    <video :src="viewer.url" x-on:click.stop controls autoplay class="max-h-full max-w-full rounded-lg"></video>
                </template>
            </div>
        </div>
    </template>
</div>
