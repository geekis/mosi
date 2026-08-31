<x-layouts::auth :title="__('Set your password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Welcome to Mosi')" :description="__('Set a password to activate your account')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('invitation.store') }}" class="flex flex-col gap-6">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <x-ui.input
                name="email"
                value="{{ $email }}"
                :label="__('Email')"
                type="email"
                required
                readonly
                autocomplete="email"
            />

            <x-ui.input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autofocus
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <x-ui.input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <x-ui.button type="submit" variant="primary" class="w-full" data-test="accept-invitation-button">
                    {{ __('Set password and sign in') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</x-layouts::auth>
