<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<x-ui.modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <x-ui.heading size="lg">{{ __('Are you sure you want to delete your account?') }}</x-ui.heading>

            <x-ui.subheading>
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </x-ui.subheading>
        </div>

        <x-ui.input wire:model="password" :label="__('Password')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <x-ui.modal.close>
                <x-ui.button variant="filled">{{ __('Cancel') }}</x-ui.button>
            </x-ui.modal.close>

            <x-ui.button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
