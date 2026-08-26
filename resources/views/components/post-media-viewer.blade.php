{{--
    Fullscreen image/video viewer shared by post cards. The host element must define the
    Alpine `viewer` state plus `open(kind, url, name)` and `close()` — the feed and group
    page roots do. Click-outside or Esc closes it; the download link uses the media's name.
--}}
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
