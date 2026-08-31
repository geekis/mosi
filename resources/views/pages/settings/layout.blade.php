<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <x-ui.navlist aria-label="{{ __('Settings') }}">
            <x-ui.navlist.item :href="route('profile.edit')" :current="request()->routeIs('profile.edit')">{{ __('Profile') }}</x-ui.navlist.item>
            <x-ui.navlist.item :href="route('security.edit')" :current="request()->routeIs('security.edit')">{{ __('Security') }}</x-ui.navlist.item>
            <x-ui.navlist.item :href="route('notifications.edit')" :current="request()->routeIs('notifications.edit')">{{ __('Notifications') }}</x-ui.navlist.item>
        </x-ui.navlist>
    </div>

    <x-ui.separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <x-ui.heading>{{ $heading ?? '' }}</x-ui.heading>
        <x-ui.subheading>{{ $subheading ?? '' }}</x-ui.subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
