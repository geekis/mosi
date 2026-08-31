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

<x-ui.dropdown position="bottom" align="start">
    <x-slot:trigger>
        <x-ui.button icon="squares-2x2" variant="ghost" size="sm" :aria-label="__('Shortcuts')" />
    </x-slot:trigger>

    <div class="w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-sand-200/80 bg-white p-3 shadow-soft-lg sm:w-[28rem] dark:border-white/10 dark:bg-zinc-900">
        @if ($shortcuts->isEmpty())
            <x-ui.text size="sm" class="px-2 py-6 text-center">
                {{ __('No shortcuts yet.') }}
            </x-ui.text>
        @else
            <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ($shortcuts as $category => $items)
                    <div wire:key="shortcut-group-{{ $loop->index }}">
                        @if ($category !== '')
                            <x-ui.heading size="sm" class="mb-1 px-2 text-mist-500">{{ $category }}</x-ui.heading>
                        @endif

                        @foreach ($items as $shortcut)
                            <a
                                wire:key="shortcut-{{ $shortcut->id }}"
                                href="{{ $shortcut->url }}"
                                @if ($shortcut->opens_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                                class="flex items-start gap-3 rounded-xl p-2 hover:bg-sand-100 dark:hover:bg-white/5"
                            >
                                @if ($shortcut->hasCustomSvg())
                                    {{-- Sanitised on save; safe to render. --}}
                                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center text-mist-500 [&>svg]:size-5 [&>svg]:w-auto">
                                        {!! $shortcut->svg !!}
                                    </span>
                                @else
                                    <x-ui.icon
                                        :name="$shortcut->iconName()"
                                        variant="mini"
                                        class="mt-0.5 size-5 shrink-0 text-mist-400"
                                    />
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-ink-800 dark:text-zinc-200">{{ $shortcut->label }}</div>
                                    @if ($shortcut->description)
                                        <div class="truncate text-xs text-mist-500">{{ $shortcut->description }}</div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-ui.dropdown>
