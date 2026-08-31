<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-sand-50 dark:bg-zinc-800">
        <div
            x-data="{ nav: false, collapsed: localStorage.getItem('sidebar-collapsed') === '1' }"
            x-effect="localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0')"
            x-bind:data-collapsed="collapsed ? 'true' : 'false'"
            class="group/shell"
        >
            {{-- Mobile backdrop --}}
            <div
                x-show="nav"
                x-cloak
                x-transition.opacity
                @click="nav = false"
                class="fixed inset-0 z-30 bg-ink-950/40 lg:hidden"
            ></div>

            {{-- Left rail: fixed on desktop, off-canvas drawer on mobile. --}}
            <aside
                data-sidebar
                class="fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-sand-200/70 bg-sand-50/90 backdrop-blur-xl transition-all duration-200 lg:w-64 group-data-[collapsed=true]/shell:lg:w-16 dark:border-zinc-700 dark:bg-zinc-900"
                x-bind:class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                @keydown.escape.window="nav = false"
            >
                <div class="flex h-16 shrink-0 items-center justify-between gap-1 px-4 group-data-[collapsed=true]/shell:lg:justify-center group-data-[collapsed=true]/shell:lg:px-2">
                    <span data-hide-in-teams class="group-data-[collapsed=true]/shell:lg:hidden">
                        <x-app-logo :href="route('dashboard')" wire:navigate />
                    </span>
                    <div class="flex items-center gap-1">
                        {{-- Desktop: collapse to an icon rail (persisted in localStorage). --}}
                        <button type="button" @click="collapsed = ! collapsed"
                            class="hidden rounded-lg p-1.5 text-taupe-600 hover:bg-sand-200 lg:inline-flex"
                            :aria-label="collapsed ? @js(__('Expand sidebar')) : @js(__('Collapse sidebar'))">
                            <x-ui.icon name="chevron-double-left" variant="mini" x-show="!collapsed" class="size-5" />
                            <x-ui.icon name="chevron-double-right" variant="mini" x-show="collapsed" x-cloak class="size-5" />
                        </button>
                        {{-- Mobile: close the drawer. --}}
                        <button type="button" @click="nav = false" class="rounded-lg p-1.5 text-taupe-600 hover:bg-sand-200 lg:hidden" :aria-label="@js(__('Close'))">
                            <x-ui.icon name="x-mark" variant="mini" class="size-5" />
                        </button>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-3 pb-4">
                    <x-ui.navlist @click="nav = false">
                        <x-ui.navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')">{{ __('Home') }}</x-ui.navlist.item>
                        <x-ui.navlist.item icon="users" :href="route('directory')" :current="request()->routeIs('directory')">{{ __('Directory') }}</x-ui.navlist.item>

                        {{-- Whereabouts: expandable group. When the rail is open, the button
                             expands an inline submenu (`expanded`). When the rail is collapsed,
                             the submenu can't fit, so a click opens a floating flyout to the
                             right of the icon (`flyout`, teleported past the sidebar overflow).
                             `expanded`/`flyout` are separate from railTooltip's own `open`. --}}
                        <div
                            x-data="{
                                expanded: @js(request()->routeIs('whereabouts*')),
                                flyout: false,
                                fx: 0,
                                fy: 0,
                                onTrigger(event) {
                                    const btn = event.currentTarget;
                                    const collapsedRail = !! btn.closest('[data-collapsed=true]') && window.matchMedia('(min-width: 1024px)').matches;
                                    if (! collapsedRail) { this.expanded = ! this.expanded; return; }
                                    if (this.flyout) { this.flyout = false; return; }
                                    const r = btn.getBoundingClientRect();
                                    this.fx = r.right + 12;
                                    this.fy = r.top;
                                    this.flyout = true;
                                },
                            }"
                            @click.outside="flyout = false"
                            @keydown.escape.window="flyout = false"
                        >
                            <button type="button"
                                @click="onTrigger($event); open = false"
                                x-data="railTooltip()"
                                data-tooltip="{{ __('Whereabouts') }}"
                                x-on:mouseenter="enter()"
                                x-on:mouseleave="leave()"
                                class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-taupe-600 transition-colors hover:bg-sand-100 hover:text-ink-900 group-data-[collapsed=true]/shell:lg:justify-center dark:text-zinc-400 dark:hover:text-white">
                                <x-ui.icon name="map-pin" variant="mini" class="size-5 shrink-0" />
                                <span class="flex-1 text-start group-data-[collapsed=true]/shell:lg:hidden">{{ __('Whereabouts') }}</span>
                                <x-ui.icon name="chevron-down" variant="mini" class="size-4 shrink-0 transition-transform group-data-[collapsed=true]/shell:lg:hidden" x-bind:class="expanded && 'rotate-180'" />

                                <template x-teleport="body">
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition.opacity.duration.100ms
                                        class="pointer-events-none fixed z-[70] -translate-y-1/2 whitespace-nowrap rounded-lg bg-ink-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-soft-lg"
                                        :style="`left:${x}px; top:${y}px`"
                                    >
                                        <span class="absolute -start-1 top-1/2 size-2 -translate-y-1/2 rotate-45 rounded-[2px] bg-ink-900"></span>
                                        <span x-text="label"></span>
                                    </div>
                                </template>
                            </button>

                            {{-- Inline submenu (rail open). --}}
                            <div x-show="expanded" x-cloak x-transition class="mt-0.5 space-y-0.5 ps-9 group-data-[collapsed=true]/shell:lg:hidden">
                                <x-ui.navlist.item :href="route('whereabouts')" :current="request()->routeIs('whereabouts')">{{ __('Me') }}</x-ui.navlist.item>
                                <x-ui.navlist.item :href="route('whereabouts.others')" :current="request()->routeIs('whereabouts.others')">{{ __('Other staff') }}</x-ui.navlist.item>
                            </div>

                            {{-- Flyout submenu (rail collapsed): floats to the right of the icon. --}}
                            <template x-teleport="body">
                                <div
                                    x-show="flyout"
                                    x-cloak
                                    x-transition.opacity.duration.100ms
                                    :style="`left:${fx}px; top:${fy}px`"
                                    class="fixed z-[70] min-w-44 rounded-xl border border-sand-200/80 bg-white p-1.5 shadow-soft-lg dark:border-white/10 dark:bg-zinc-900"
                                >
                                    <div class="px-2.5 pb-1 pt-0.5 text-xs font-semibold text-mist-400">{{ __('Whereabouts') }}</div>
                                    <a href="{{ route('whereabouts') }}" wire:navigate @click="flyout = false"
                                        @class([
                                            'block rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors',
                                            'bg-sand-100 text-ink-900 dark:bg-white/10 dark:text-white' => request()->routeIs('whereabouts'),
                                            'text-taupe-600 hover:bg-sand-100 hover:text-ink-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('whereabouts'),
                                        ])>{{ __('Me') }}</a>
                                    <a href="{{ route('whereabouts.others') }}" wire:navigate @click="flyout = false"
                                        @class([
                                            'block rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors',
                                            'bg-sand-100 text-ink-900 dark:bg-white/10 dark:text-white' => request()->routeIs('whereabouts.others'),
                                            'text-taupe-600 hover:bg-sand-100 hover:text-ink-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('whereabouts.others'),
                                        ])>{{ __('Other staff') }}</a>
                                </div>
                            </template>
                        </div>

                        <x-ui.navlist.item icon="user-group" :href="route('groups')" :current="request()->routeIs('groups', 'groups.show')">{{ __('Groups') }}</x-ui.navlist.item>
                        <x-ui.navlist.item icon="calendar-days" :href="route('events')" :current="request()->routeIs('events', 'events.show')">{{ __('Events') }}</x-ui.navlist.item>
                        <livewire:pages::chat-nav-item />
                        <x-ui.navlist.item icon="newspaper" :href="route('news')" :current="request()->routeIs('news')">{{ __('News') }}</x-ui.navlist.item>
                        <x-ui.navlist.item icon="document-text" :href="route('documents')" :current="request()->routeIs('documents')">{{ __('Documents') }}</x-ui.navlist.item>
                    </x-ui.navlist>

                    {{-- The user's groups with a live unread-post badge. --}}
                    <livewire:pages::my-groups-nav />

                    <div class="flex-1"></div>

                    <x-ui.navlist @click="nav = false">
                        <x-ui.navlist.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit')">{{ __('Settings') }}</x-ui.navlist.item>
                    </x-ui.navlist>
                </div>
            </aside>

            {{-- Main column --}}
            <div class="transition-[padding] duration-200 lg:ps-64 group-data-[collapsed=true]/shell:lg:ps-16">
                {{-- Top bar --}}
                <header class="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-sand-200/70 bg-sand-50/90 px-4 backdrop-blur-xl dark:border-zinc-700 dark:bg-zinc-900">
                    <button type="button" @click="nav = true" class="me-1 rounded-lg p-1.5 text-taupe-600 hover:bg-sand-200 lg:hidden" data-hide-in-teams :aria-label="@js(__('Open navigation'))">
                        <x-ui.icon name="bars-2" variant="mini" class="size-6" />
                    </button>

                    {{-- Mega menu of staff shortcuts, then global search. --}}
                    <livewire:pages::shortcut-menu />

                    <form method="GET" action="{{ route('search') }}" class="ms-2 w-full max-w-xs">
                        <x-ui.input type="search" name="q" icon="magnifying-glass" :placeholder="__('Search…')" size="sm" />
                    </form>

                    <div class="flex-1"></div>

                    <div class="me-2 flex items-center gap-1">
                        <x-ui.button :href="route('profile.edit')" icon="cog-6-tooth" variant="ghost" size="sm" :aria-label="__('Settings')" />
                        <livewire:pages::notification-bell />
                    </div>

                    {{-- Account menu — hidden inside the Teams tab. --}}
                    <x-ui.dropdown align="end" data-hide-in-teams>
                        <x-slot:trigger>
                            <button type="button" class="flex items-center gap-1.5 rounded-full p-0.5 hover:bg-sand-200" :aria-label="@js(__('Account menu'))">
                                <x-avatar :user="auth()->user()" :presence="false" size="sm" />
                                <x-ui.icon name="chevron-down" variant="mini" class="size-4 text-mist-500" />
                            </button>
                        </x-slot:trigger>

                        <x-ui.menu class="w-56">
                            <div class="flex items-center gap-2 px-2 py-1.5 text-start text-sm">
                                <x-avatar :user="auth()->user()" :presence="false" size="sm" />
                                <div class="grid min-w-0 flex-1 text-start leading-tight">
                                    <span class="truncate font-medium text-ink-900 dark:text-white">{{ auth()->user()->display_name }}</span>
                                    <span class="truncate text-xs text-mist-500">{{ auth()->user()->email }}</span>
                                </div>
                            </div>

                            <x-ui.menu.separator />

                            <x-ui.menu.item :href="route('profile.edit')" icon="cog">{{ __('Settings') }}</x-ui.menu.item>

                            <x-ui.menu.separator />

                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <x-ui.menu.item type="submit" icon="arrow-right-start-on-rectangle" data-test="logout-button">
                                    {{ __('Log out') }}
                                </x-ui.menu.item>
                            </form>
                        </x-ui.menu>
                    </x-ui.dropdown>
                </header>

                {{ $slot }}
            </div>
        </div>

        @persist('toast')
            <x-ui.toasts />
        @endpersist
    </body>
</html>
