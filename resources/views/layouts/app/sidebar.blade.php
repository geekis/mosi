<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{-- Left nav: brand, search, primary nav, and a secondary nav pinned to the bottom. --}}
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            {{-- Global search: submits to the search page. A Meilisearch-backed command
                 palette is the eventual upgrade; the query is visibility-scoped either way. --}}
            <form method="GET" action="{{ route('search') }}">
                <flux:sidebar.search name="q" :placeholder="__('Search…')" />
            </form>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Home') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="users" :href="route('directory')" :current="request()->routeIs('directory')" wire:navigate>
                    {{ __('Directory') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="calendar-days" :href="route('events')" :current="request()->routeIs('events', 'events.show')" wire:navigate>
                    {{ __('Events') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="chat-bubble-left-right" :href="route('chat')" :current="request()->routeIs('chat')" wire:navigate>
                    {{ __('Chat') }}
                </flux:sidebar.item>
                {{-- The Groups section arrives with a later phase. --}}
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'appearance.edit', 'security.edit')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{-- Top nav: mobile sidebar toggle, breadcrumb, then account actions on the right. --}}
        <flux:header container class="border-b border-zinc-200 bg-white lg:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="me-4 lg:hidden" icon="bars-2" inset="left" />

            <flux:breadcrumbs class="max-sm:hidden">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home">{{ __('Home') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:spacer />

            <div class="me-4 flex items-center gap-1">
                <flux:button :href="route('profile.edit')" icon="cog-6-tooth" variant="ghost" size="sm" aria-label="{{ __('Settings') }}" wire:navigate />
                <livewire:pages::notification-bell />
            </div>

            <flux:dropdown align="end">
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
