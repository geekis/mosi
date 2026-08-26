@props(['reactions' => []])

{{--
    Lists who reacted to a post and with which emoji. Populated by the host's
    showReactions() into $postReactionList; there is one instance per page.
--}}
<flux:modal name="post-reactions" class="w-full max-w-md">
    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Reactions') }}</flux:heading>

        <div class="max-h-96 space-y-1 overflow-y-auto">
            @forelse ($reactions as $i => $reaction)
                <div wire:key="reaction-row-{{ $i }}" class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <div class="relative shrink-0">
                        <flux:avatar :name="$reaction['name']" :initials="$reaction['initials']" :src="$reaction['avatar']" size="sm" />
                        <span class="absolute -bottom-1 -right-1 text-sm">{{ $reaction['emoji'] }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        @if ($reaction['url'])
                            <a href="{{ $reaction['url'] }}" wire:navigate class="block truncate font-medium hover:underline">{{ $reaction['name'] }}</a>
                        @else
                            <span class="block truncate font-medium">{{ $reaction['name'] }}</span>
                        @endif
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $reaction['label'] }}</flux:text>
                    </div>
                </div>
            @empty
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No reactions yet.') }}</flux:text>
            @endforelse
        </div>
    </div>
</flux:modal>
