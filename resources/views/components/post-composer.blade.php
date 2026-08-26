@props([
    'me',
    'placeholder' => '',
    'attachments' => [],
    'mentionables' => [],
    'postTest' => 'publish-button',
])

{{--
    The shared post composer for the feed and group pages. It binds to the host Livewire
    component's `composer` / `attachments` state and its `publish` / `removeAttachment`
    methods (provided by the InteractsWithPostComposer trait). Photos, video and files go
    through one multi-file input; emoji come from the <emoji-picker> web component.
--}}
<div>
    {{-- Trigger card --}}
    <flux:card class="pb-2">
        <div class="flex items-center gap-3">
            <flux:avatar :name="$me->display_name" :initials="$me->initials()" :src="$me->avatarUrl()" size="sm" />

            <flux:modal.trigger name="composer" class="flex-1">
                <button type="button" class="w-full rounded-full bg-zinc-100 px-4 py-2 text-start text-sm text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600">
                    {{ __("What's going on, :name?", ['name' => $me->given_name ?? $me->display_name]) }}
                </button>
            </flux:modal.trigger>
        </div>

        <flux:separator class="my-3" variant="subtle" />

        <div class="flex flex-wrap items-center justify-between gap-1">
            <flux:modal.trigger name="composer">
                <flux:button size="sm" variant="ghost" icon="photo">{{ __('Photo/video') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="composer">
                <flux:button size="sm" variant="ghost" icon="paper-clip">{{ __('File') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="composer">
                <flux:button size="sm" variant="ghost" icon="face-smile">{{ __('Emoji') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </flux:card>

    {{-- Composer modal --}}
    <flux:modal name="composer" class="w-full max-w-lg">
        <div class="space-y-4"
            x-data="{
                showEmoji: false,
                insertEmoji(unicode) {
                    const ta = $refs.composerInput.querySelector('textarea') ?? $refs.composerInput;
                    const start = ta.selectionStart ?? ta.value.length;
                    const end = ta.selectionEnd ?? ta.value.length;
                    ta.value = ta.value.slice(0, start) + unicode + ta.value.slice(end);
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                    ta.focus();
                    const caret = start + unicode.length;
                    ta.setSelectionRange(caret, caret);
                    this.showEmoji = false;
                },
            }">
            <flux:heading size="lg">{{ __('Create a post') }}</flux:heading>

            <div x-ref="composerInput">
                <x-mention-textarea wire:model="composer" :placeholder="$placeholder" :rows="4" :mentionables="$mentionables" />
            </div>

            {{-- Pending uploads, removable before publishing --}}
            @if (filled($attachments))
                <div class="flex flex-wrap gap-2">
                    @foreach ($attachments as $index => $file)
                        <div wire:key="post-pending-{{ $index }}" class="flex items-center gap-2 rounded-lg bg-zinc-100 py-1 pe-1 ps-2 text-xs dark:bg-zinc-800">
                            @if ($file->isPreviewable() && str_starts_with((string) $file->getMimeType(), 'image/'))
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

            <flux:error name="attachments" />
            <flux:error name="attachments.*" />

            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <input type="file" multiple wire:model="attachments" x-ref="postFiles" class="hidden" />
                    <flux:button type="button" size="sm" variant="ghost" icon="photo"
                        x-on:click="$refs.postFiles.click()" :aria-label="__('Add photos or video')" />
                    <flux:button type="button" size="sm" variant="ghost" icon="paper-clip"
                        x-on:click="$refs.postFiles.click()" :aria-label="__('Add file')" />

                    {{-- Emoji picker: the web component keeps its own shadow-DOM state, so
                         wire:ignore stops Livewire morphing from wiping it on re-render. --}}
                    <div class="relative">
                        <flux:button type="button" size="sm" variant="ghost" icon="face-smile"
                            x-on:click="showEmoji = ! showEmoji" :aria-label="__('Emoji')" />
                        <div x-show="showEmoji" x-cloak x-transition x-on:click.outside="showEmoji = false"
                            class="absolute bottom-full left-0 z-50 mb-2" wire:ignore>
                            <emoji-picker x-on:emoji-click="insertEmoji($event.detail.unicode)"></emoji-picker>
                        </div>
                    </div>
                </div>

                <flux:button variant="primary" wire:click="publish" data-test="{{ $postTest }}">
                    {{ __('Post') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
