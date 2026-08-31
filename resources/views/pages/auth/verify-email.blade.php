<x-layouts::auth :title="__('Email verification')">
    <div class="mt-4 flex flex-col gap-6">
        <x-ui.text class="text-center">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </x-ui.text>

        @if (session('status') == 'verification-link-sent')
            <x-ui.text class="text-center font-medium text-brand-600 dark:text-brand-400">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </x-ui.text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-ui.button type="submit" variant="primary" class="w-full">
                    {{ __('Resend verification email') }}
                </x-ui.button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button variant="ghost" size="sm" type="submit" class="cursor-pointer" data-test="logout-button">
                    {{ __('Log out') }}
                </x-ui.button>
            </form>
        </div>
    </div>
</x-layouts::auth>
