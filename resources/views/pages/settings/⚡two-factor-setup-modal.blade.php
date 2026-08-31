<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(): void
    {
        // Reset any lingering state so re-opening the modal always starts fresh.
        $this->reset('code', 'showVerificationStep', 'setupComplete');
        $this->resetErrorBag();

        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch('two-factor-enabled');
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;

        $this->closeModal();

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
        );

        $this->resetErrorBag();

        $this->dispatch('modal-close', name: 'two-factor-setup-modal');
    }

    /**
     * Get the current modal configuration state.
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->setupComplete) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
}; ?>

<x-ui.modal name="two-factor-setup-modal" class="max-w-md">
    <div class="space-y-6">
        <div class="flex flex-col items-center space-y-4">
            <div class="p-0.5 w-auto rounded-full border border-sand-200 bg-white shadow-soft">
                <div class="p-2.5 rounded-full border border-sand-200 overflow-hidden bg-sand-100 relative">
                    <div class="flex items-stretch absolute inset-0 w-full h-full divide-x [&>div]:flex-1 divide-sand-200 justify-around opacity-50">
                        @for ($i = 1; $i <= 5; $i++)
                            <div></div>
                        @endfor
                    </div>

                    <div class="flex flex-col items-stretch absolute w-full h-full divide-y [&>div]:flex-1 inset-0 divide-sand-200 justify-around opacity-50">
                        @for ($i = 1; $i <= 5; $i++)
                            <div></div>
                        @endfor
                    </div>

                    <x-ui.icon name="qr-code" class="relative z-20 size-6 text-ink-900" />
                </div>
            </div>

            <div class="space-y-2 text-center">
                <x-ui.heading size="lg">{{ $this->modalConfig['title'] }}</x-ui.heading>
                <x-ui.text>{{ $this->modalConfig['description'] }}</x-ui.text>
            </div>
        </div>

        @if ($showVerificationStep)
            <div class="space-y-6">
                <div
                    class="flex flex-col items-center space-y-3 justify-center"
                    x-data
                    x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                >
                    <x-ui.otp
                        name="code"
                        wire:model="code"
                        :digits="6"
                        aria-label="OTP Code"
                        class="mx-auto max-w-xs"
                    />
                </div>

                <div class="flex items-center space-x-3">
                    <x-ui.button
                        variant="secondary"
                        class="flex-1"
                        wire:click="resetVerification"
                    >
                        {{ __('Back') }}
                    </x-ui.button>

                    <x-ui.button
                        variant="primary"
                        class="flex-1"
                        wire:click="confirmTwoFactor"
                        x-bind:disabled="$wire.code.length < 6"
                    >
                        {{ __('Confirm') }}
                    </x-ui.button>
                </div>
            </div>
        @else
            @error('setupData')
                <x-ui.alert variant="danger" icon="x-circle">{{ $message }}</x-ui.alert>
            @enderror

            <div class="flex justify-center">
                <div class="relative w-64 overflow-hidden border rounded-lg border-sand-200 aspect-square">
                    @empty($qrCodeSvg)
                        <div class="absolute inset-0 flex items-center justify-center bg-white animate-pulse text-mist-400">
                            <x-ui.icon name="loading" />
                        </div>
                    @else
                        <div class="flex items-center justify-center h-full p-4">
                            <div class="bg-white p-3 rounded">
                                {!! $qrCodeSvg !!}
                            </div>
                        </div>
                    @endempty
                </div>
            </div>

            <div>
                <x-ui.button
                    :disabled="$errors->has('setupData')"
                    variant="primary"
                    class="w-full"
                    wire:click="showVerificationIfNecessary"
                >
                    {{ $this->modalConfig['buttonText'] }}
                </x-ui.button>
            </div>

            <div class="space-y-4">
                <div class="relative flex items-center justify-center w-full">
                    <div class="absolute inset-0 w-full h-px top-1/2 bg-sand-200"></div>
                    <span class="relative px-2 text-sm bg-white text-mist-500">
                        {{ __('or, enter the code manually') }}
                    </span>
                </div>

                <div
                    class="flex items-center space-x-2"
                    x-data="{
                        copied: false,
                        async copy() {
                            try {
                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            } catch (e) {
                                console.warn('Could not copy to clipboard');
                            }
                        }
                    }"
                >
                    <div class="flex items-stretch w-full border rounded-xl border-sand-200">
                        @empty($manualSetupKey)
                            <div class="flex items-center justify-center w-full p-3 bg-sand-100 text-mist-400">
                                <x-ui.icon name="loading" variant="mini" />
                            </div>
                        @else
                            <input
                                type="text"
                                readonly
                                value="{{ $manualSetupKey }}"
                                class="w-full p-3 bg-transparent outline-none text-ink-900"
                            />

                            <button
                                type="button"
                                @click="copy()"
                                class="px-3 transition-colors border-l cursor-pointer border-sand-200"
                            >
                                <x-ui.icon name="document-duplicate" x-show="!copied" variant="outline" class="size-5 text-mist-500" />
                                <x-ui.icon name="check" x-show="copied" x-cloak variant="solid" class="size-5 text-brand-500" />
                            </button>
                        @endempty
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-ui.modal>
