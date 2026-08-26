@props(['placement' => 'bottom'])

{{--
    The @mention autocomplete dropdown. Must be rendered inside an element with
    x-data="mentionBox(...)" — it reads that scope's `open`, `results`, `activeIndex` and
    `select`. Placement is `bottom` for a composer that sits above its content, or `top`
    for the chat input pinned to the bottom of the screen (the list opens upward).
--}}
<div x-show="open" x-cloak
    @class([
        'absolute inset-x-0 z-50 max-h-56 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800',
        'mt-1' => $placement === 'bottom',
        'bottom-full mb-1' => $placement === 'top',
    ])>
    <template x-for="(person, index) in results" :key="person.handle">
        <button type="button"
            x-on:mousedown.prevent="select(person)"
            x-on:mouseenter="activeIndex = index"
            :class="index === activeIndex ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm">
            <span class="font-medium" x-text="person.name"></span>
            <span class="text-zinc-400 dark:text-zinc-500" x-text="'@' + person.handle"></span>
        </button>
    </template>
</div>
