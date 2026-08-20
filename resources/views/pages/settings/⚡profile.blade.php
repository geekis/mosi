<?php

use App\Actions\Profile\UpdateProfilePhoto;
use App\Models\User;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use WithFileUploads;

    public string $display_name = '';

    public string $email = '';

    public string $bio = '';

    public ?TemporaryUploadedFile $avatar = null;

    public ?TemporaryUploadedFile $banner = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->display_name = Auth::user()->display_name;
        $this->email = Auth::user()->email;
        $this->bio = (string) Auth::user()->bio;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Store a newly chosen avatar or banner. Each is validated as an image here, then
     * the action swaps the single-file media collection.
     */
    public function saveAvatar(UpdateProfilePhoto $updateProfilePhoto): void
    {
        $this->validate(['avatar' => ['required', 'image', 'max:5120']]);

        $updateProfilePhoto(Auth::user(), $this->avatar, 'avatar');

        $this->avatar = null;
        Flux::toast(variant: 'success', text: __('Photo updated.'));
    }

    public function saveBanner(UpdateProfilePhoto $updateProfilePhoto): void
    {
        $this->validate(['banner' => ['required', 'image', 'max:8192']]);

        $updateProfilePhoto(Auth::user(), $this->banner, 'banner');

        $this->banner = null;
        Flux::toast(variant: 'success', text: __('Banner updated.'));
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        {{-- Photo & banner: each saves on its own, separate from the details form. --}}
        <div class="my-6 flex items-center gap-4">
            <flux:avatar :name="auth()->user()->display_name" :initials="auth()->user()->initials()" :src="$avatar && $avatar->isPreviewable() ? $avatar->temporaryUrl() : auth()->user()->avatarUrl()" size="xl" />
            <div class="space-y-2">
                <flux:input type="file" wire:model="avatar" :label="__('Profile photo')" accept="image/*" class="max-w-xs" />
                <flux:error name="avatar" />
                @if ($avatar)
                    <flux:button size="sm" variant="primary" wire:click="saveAvatar" data-test="save-avatar-button">{{ __('Save photo') }}</flux:button>
                @endif
            </div>
        </div>

        <div class="my-6 space-y-2">
            <flux:input type="file" wire:model="banner" :label="__('Profile banner')" accept="image/*" class="max-w-xs" />
            <flux:error name="banner" />
            @if ($banner)
                <flux:button size="sm" variant="primary" wire:click="saveBanner" data-test="save-banner-button">{{ __('Save banner') }}</flux:button>
            @endif
        </div>

        <flux:separator variant="subtle" />

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="display_name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            <flux:textarea wire:model="bio" :label="__('About')" :placeholder="__('A short introduction shown on your profile.')" rows="4" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
