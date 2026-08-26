<?php

use App\Models\Shortcut;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The header mega menu: staff shortcuts to systems and information, managed by admins
 * in the Filament panel. Every authenticated user sees the active shortcuts, grouped
 * into sections by their optional category.
 */
new class extends Component {
    /**
     * Active shortcuts grouped by category, uncategorised last.
     *
     * @return array{shortcuts: Collection<string, Collection<int, Shortcut>>}
     */
    public function with(): array
    {
        $grouped = Shortcut::query()
            ->active()
            ->get()
            ->groupBy(fn (Shortcut $shortcut): string => $shortcut->category ?? '');

        return [
            'shortcuts' => $grouped->sortKeys(),
        ];
    }
}; ?>

<flux:dropdown position="bottom" align="start">
    <flux:button icon="squares-2x2" variant="ghost" size="sm" :aria-label="__('Shortcuts')" />

    <flux:popover class="max-w-[calc(100vw-2rem)] w-80 sm:w-[28rem]">
        @if ($shortcuts->isEmpty())
            <flux:text size="sm" class="px-2 py-6 text-center text-zinc-500">
                {{ __('No shortcuts yet.') }}
            </flux:text>
        @else
            <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ($shortcuts as $category => $items)
                    <div wire:key="shortcut-group-{{ $loop->index }}">
                        @if ($category !== '')
                            <flux:heading size="sm" class="mb-1 px-2 text-zinc-500">{{ $category }}</flux:heading>
                        @endif

                        @foreach ($items as $shortcut)
                            <a
                                wire:key="shortcut-{{ $shortcut->id }}"
                                href="{{ $shortcut->url }}"
                                @if ($shortcut->opens_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                                class="flex items-start gap-3 rounded-lg p-2 hover:bg-zinc-100"
                            >
                                @if ($shortcut->hasCustomSvg())
                                    {{-- Sanitised on save; safe to render. --}}
                                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center text-zinc-500 [&>svg]:size-5 [&>svg]:w-auto">
                                        {!! $shortcut->svg !!}
                                    </span>
                                @else
                                    <flux:icon
                                        :icon="$shortcut->iconName()"
                                        class="mt-0.5 size-5 shrink-0 text-zinc-400"
                                    />
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-zinc-800">{{ $shortcut->label }}</div>
                                    @if ($shortcut->description)
                                        <div class="truncate text-xs text-zinc-500">{{ $shortcut->description }}</div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </flux:popover>
</flux:dropdown>
