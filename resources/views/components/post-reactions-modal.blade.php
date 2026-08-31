@props(['reactions' => []])

{{--
    Lists who reacted to a post and with which emoji. Populated by the host's
    showReactions() into $postReactionList; there is one instance per page.
--}}
<x-ui.modal name="post-reactions" class="max-w-md">
    <div class="space-y-4">
        <x-ui.heading size="lg">{{ __('Reactions') }}</x-ui.heading>

        <div class="max-h-96 space-y-1 overflow-y-auto">
            @forelse ($reactions as $i => $reaction)
                <div wire:key="reaction-row-{{ $i }}" class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <div class="relative shrink-0">
                        <x-avatar :name="$reaction['name']" :initials="$reaction['initials']" :src="$reaction['avatar']" :presence="false" size="sm" />
                        <span class="absolute -bottom-1 -right-1 text-sm">{{ $reaction['emoji'] }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        @if ($reaction['url'])
                            <a href="{{ $reaction['url'] }}" wire:navigate class="block truncate font-medium hover:underline">{{ $reaction['name'] }}</a>
                        @else
                            <span class="block truncate font-medium">{{ $reaction['name'] }}</span>
                        @endif
                        <x-ui.text size="sm">{{ $reaction['label'] }}</x-ui.text>
                    </div>
                </div>
            @empty
                <x-ui.text size="sm">{{ __('No reactions yet.') }}</x-ui.text>
            @endforelse
        </div>
    </div>
</x-ui.modal>
