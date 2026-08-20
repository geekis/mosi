<x-filament-panels::page>
    <div class="space-y-3">
        @forelse ($this->recentRuns() as $run)
            <div class="flex items-center justify-between rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div>
                    <p class="font-medium">{{ $run->created_at?->format('d.m.Y H:i') }}</p>
                    <p class="text-sm text-gray-500">{{ $run->status }}</p>
                </div>
                <div class="text-sm text-gray-500">
                    +{{ $run->created_count }} / ~{{ $run->updated_count }} / -{{ $run->deactivated_count }}
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">{{ __('No sync runs recorded yet.') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
