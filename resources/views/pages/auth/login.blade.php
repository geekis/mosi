<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Log in to your account')"
            :description="$revealPassword ? __('Enter your password to continue') : __('Enter your email to continue')"
        />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @unless ($revealPassword)
            {{-- Step one: identify. A passkey, or the email that decides Microsoft vs password. --}}
            <x-passkey-verify />

            <form method="POST" action="{{ route('login.identify') }}" class="flex flex-col gap-6">
                @csrf

                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <flux:button variant="primary" type="submit" class="w-full" data-test="continue-button">
                    {{ __('Continue') }}
                </flux:button>
            </form>
        @else
            {{-- Step two: password for a non-Microsoft account. --}}
            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
                @csrf

                <input type="hidden" name="email" value="{{ old('email') }}" />

                <div class="flex items-center justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ old('email') }}</span>
                    <flux:link :href="route('login')" wire:navigate>{{ __('Use a different email') }}</flux:link>
                </div>

                <!-- Password -->
                <div class="relative">
                    <flux:input
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autofocus
                        autocomplete="current-password"
                        :placeholder="__('Password')"
                        viewable
                    />

                    @if (Route::has('password.request'))
                        <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </flux:link>
                    @endif
                </div>

                <!-- Remember Me -->
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </form>
        @endunless
    </div>
</x-layouts::auth>
