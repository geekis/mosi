<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#FFFDF8] dark:bg-zinc-800">
        {{-- Left nav: brand, search, primary nav, and a secondary nav pinned to the bottom. --}}
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-[#FFFDF8] dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <span data-hide-in-teams>
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                </span>
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Home') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="users" :href="route('directory')" :current="request()->routeIs('directory')" wire:navigate>
                    {{ __('Directory') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="user-group" :href="route('groups')" :current="request()->routeIs('groups', 'groups.show')" wire:navigate>
                    {{ __('Groups') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="calendar-days" :href="route('events')" :current="request()->routeIs('events', 'events.show')" wire:navigate>
                    {{ __('Events') }}
                </flux:sidebar.item>
                <livewire:pages::chat-nav-item />
                <flux:sidebar.item icon="newspaper" :href="route('news')" :current="request()->routeIs('news')" wire:navigate>
                    {{ __('News') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="document-text" :href="route('documents')" :current="request()->routeIs('documents')" wire:navigate>
                    {{ __('Documents') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            {{-- The groups the current user belongs to. Queried (not lazy-loaded) with the
                 media eager-loaded for the avatars; membership-scoped, so a secret group
                 only ever shows to its own members. A plain nav (not sidebar.group) is used
                 so the items stay visible as icons when the sidebar is collapsed. --}}
            @php($myGroups = auth()->user()->groups()->with('media')->get()->sortBy(fn ($group) => $group->name))

            {{-- Unread new-post counts per group, from the group_post notifications. Keyed
                 by group id; a group visit marks its notifications read (MarkGroupNotificationsRead). --}}
            @php($unreadByGroup = auth()->user()->unreadNotifications()
                ->where('type', \App\Notifications\NewGroupPost::class)
                ->get()
                ->groupBy(fn ($notification) => (int) ($notification->data['group_id'] ?? 0))
                ->map->count())

            @if ($myGroups->isNotEmpty())
                <flux:sidebar.nav>
                    {{-- Mirrors Flux's own sidebar-group label; hidden when the rail collapses. --}}
                    <div class="px-3 py-2 text-sm font-medium leading-none text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden">
                        {{ __('My groups') }}
                    </div>

                    @foreach ($myGroups as $group)
                        <flux:sidebar.item
                            :href="route('groups.show', $group)"
                            :current="request()->routeIs('groups.show') && request()->route('group')?->is($group)"
                            :badge="($unreadByGroup[$group->id] ?? 0) ?: null"
                            wire:navigate
                            wire:key="sidebar-group-{{ $group->id }}"
                        >
                            <x-slot:icon>
                                @if ($group->avatarUrl())
                                    <flux:avatar :src="$group->avatarUrl()" size="xs" />
                                @else
                                    <flux:icon icon="user-group" variant="outline" class="size-4" />
                                @endif
                            </x-slot:icon>

                            {{ $group->name }}
                        </flux:sidebar.item>
                    @endforeach
                </flux:sidebar.nav>
            @endif

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{-- Top nav: mobile sidebar toggle, shortcuts mega menu, search, then account actions on the right. --}}
        <flux:header container class="border-b border-zinc-200 bg-[#FFFDF8] lg:bg-[#FFFDF8] dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="me-2 lg:hidden" icon="bars-2" inset="left" data-hide-in-teams />

            {{-- Mega menu of staff shortcuts, then global search. --}}
            <livewire:pages::shortcut-menu />

            <form method="GET" action="{{ route('search') }}" class="ms-2 w-full max-w-xs">
                <flux:input type="search" name="q" icon="magnifying-glass" :placeholder="__('Search…')" size="sm" clearable />
            </form>

            <flux:spacer />

            <div class="me-4 flex items-center gap-1">
                <flux:button :href="route('profile.edit')" icon="cog-6-tooth" variant="ghost" size="sm" aria-label="{{ __('Settings') }}" wire:navigate />
                <livewire:pages::notification-bell />
            </div>

            {{-- Inside the Teams tab, identity is managed by Teams itself, so the account
                 menu is redundant. Hidden client-side when actually framed by Teams (see
                 partials/head), so a browser session that once visited Teams still shows it. --}}
            <flux:dropdown align="end" data-hide-in-teams>
                <flux:profile :initials="auth()->user()->initials()" :aria-label="__('Account menu')" icon-trailing="chevron-down" />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="auth()->user()->display_name" :initials="auth()->user()->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->display_name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
