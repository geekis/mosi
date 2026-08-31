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
    <x-ui.card class="pb-2">
        <div class="flex items-center gap-3">
            <x-avatar :user="$me" :presence="false" size="sm" />

            <x-ui.modal.trigger name="composer" class="flex-1">
                <button type="button" class="w-full rounded-full bg-sand-100 px-4 py-2 text-start text-sm text-mist-500 hover:bg-sand-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600">
                    {{ __("What's going on, :name?", ['name' => $me->given_name ?? $me->display_name]) }}
                </button>
            </x-ui.modal.trigger>
        </div>

        <x-ui.separator class="my-3" variant="subtle" />

        <div class="flex flex-wrap items-center justify-between gap-1">
            <x-ui.modal.trigger name="composer">
                <x-ui.button size="sm" variant="ghost" icon="photo">{{ __('Photo/video') }}</x-ui.button>
            </x-ui.modal.trigger>
            <x-ui.modal.trigger name="composer">
                <x-ui.button size="sm" variant="ghost" icon="paper-clip">{{ __('File') }}</x-ui.button>
            </x-ui.modal.trigger>
            <x-ui.modal.trigger name="composer">
                <x-ui.button size="sm" variant="ghost" icon="face-smile">{{ __('Emoji') }}</x-ui.button>
            </x-ui.modal.trigger>
        </div>
    </x-ui.card>

    {{-- Composer modal --}}
    <x-ui.modal name="composer" class="max-w-lg">
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
            <x-ui.heading size="lg">{{ __('Create a post') }}</x-ui.heading>

            <div x-ref="composerInput">
                <x-mention-textarea wire:model="composer" :placeholder="$placeholder" :rows="4" :mentionables="$mentionables" />
            </div>

            {{-- Pending uploads, removable before publishing --}}
            @if (filled($attachments))
                <div class="flex flex-wrap gap-2">
                    @foreach ($attachments as $index => $file)
                        <div wire:key="post-pending-{{ $index }}" class="flex items-center gap-2 rounded-lg bg-sand-100 py-1 pe-1 ps-2 text-xs dark:bg-zinc-800">
                            @if ($file->isPreviewable() && str_starts_with((string) $file->getMimeType(), 'image/'))
                                <img src="{{ $file->temporaryUrl() }}" alt="" class="h-8 w-8 rounded object-cover" />
                            @elseif (str_starts_with((string) $file->getMimeType(), 'video/'))
                                <x-ui.icon name="film" variant="micro" class="text-mist-400" />
                            @else
                                <x-ui.icon name="paper-clip" variant="micro" class="text-mist-400" />
                            @endif
                            <span class="max-w-[10rem] truncate">{{ $file->getClientOriginalName() }}</span>
                            <x-ui.button type="button" size="xs" variant="ghost" icon="x-mark"
                                wire:click="removeAttachment({{ $index }})" :aria-label="__('Remove')" />
                        </div>
                    @endforeach
                </div>
            @endif

            <x-ui.error name="attachments" />
            <x-ui.error name="attachments.*" />

            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1">
                    <input type="file" multiple wire:model="attachments" x-ref="postFiles" class="hidden" />
                    <x-ui.button type="button" size="sm" variant="ghost" icon="photo"
                        x-on:click="$refs.postFiles.click()" :aria-label="__('Add photos or video')" />
                    <x-ui.button type="button" size="sm" variant="ghost" icon="paper-clip"
                        x-on:click="$refs.postFiles.click()" :aria-label="__('Add file')" />

                    {{-- Emoji picker: the web component keeps its own shadow-DOM state, so
                         wire:ignore stops Livewire morphing from wiping it on re-render. --}}
                    <div class="relative">
                        <x-ui.button type="button" size="sm" variant="ghost" icon="face-smile"
                            x-on:click="showEmoji = ! showEmoji" :aria-label="__('Emoji')" />
                        <div x-show="showEmoji" x-cloak x-transition x-on:click.outside="showEmoji = false"
                            class="absolute bottom-full left-0 z-50 mb-2" wire:ignore>
                            <emoji-picker x-on:emoji-click="insertEmoji($event.detail.unicode)"></emoji-picker>
                        </div>
                    </div>
                </div>

                <x-ui.button variant="primary" wire:click="publish" data-test="{{ $postTest }}">
                    {{ __('Post') }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
