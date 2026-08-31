<?php

use App\Actions\Profile\UpdateProfilePhoto;
use App\Models\User;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
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

        $this->dispatch('toast', variant: 'success', text: __('Profile updated.'));
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
        $this->dispatch('toast', variant: 'success', text: __('Photo updated.'));
    }

    public function saveBanner(UpdateProfilePhoto $updateProfilePhoto): void
    {
        $this->validate(['banner' => ['required', 'image', 'max:8192']]);

        $updateProfilePhoto(Auth::user(), $this->banner, 'banner');

        $this->banner = null;
        $this->dispatch('toast', variant: 'success', text: __('Banner updated.'));
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

    <x-ui.heading class="sr-only">{{ __('Profile settings') }}</x-ui.heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        {{-- Photo & banner: each saves on its own, separate from the details form. --}}
        <div class="my-6 flex items-center gap-4">
            <x-avatar :name="auth()->user()->display_name" :initials="auth()->user()->initials()" :src="$avatar && $avatar->isPreviewable() ? $avatar->temporaryUrl() : auth()->user()->avatarUrl()" :presence="false" size="xl" />
            <div class="space-y-2">
                <x-ui.input type="file" wire:model="avatar" :label="__('Profile photo')" accept="image/*" class="max-w-xs" />
                @if ($avatar)
                    <x-ui.button size="sm" variant="primary" wire:click="saveAvatar" data-test="save-avatar-button">{{ __('Save photo') }}</x-ui.button>
                @endif
            </div>
        </div>

        <div class="my-6 space-y-2">
            <x-ui.input type="file" wire:model="banner" :label="__('Profile banner')" accept="image/*" class="max-w-xs" />
            @if ($banner)
                <x-ui.button size="sm" variant="primary" wire:click="saveBanner" data-test="save-banner-button">{{ __('Save banner') }}</x-ui.button>
            @endif
        </div>

        <x-ui.separator variant="subtle" />

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <x-ui.input wire:model="display_name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <x-ui.input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <x-ui.text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <x-ui.link href="#" class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </x-ui.link>
                        </x-ui.text>

                        @if (session('status') === 'verification-link-sent')
                            <x-ui.text class="mt-2 font-medium text-brand-600 dark:text-brand-400">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </x-ui.text>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            <x-ui.textarea wire:model="bio" :label="__('About')" :placeholder="__('A short introduction shown on your profile.')" rows="4" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <x-ui.button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </x-ui.button>
                </div>

            </div>
        </form>

        <x-ui.separator variant="subtle" class="my-6" />

        <div class="flex items-center justify-between">
            <div>
                <x-ui.heading size="sm">{{ __('Your data') }}</x-ui.heading>
                <x-ui.text size="sm">{{ __('Download a copy of your personal data.') }}</x-ui.text>
            </div>
            <x-ui.button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('me.export')" :navigate="false">
                {{ __('Download') }}
            </x-ui.button>
        </div>

        <x-ui.separator variant="subtle" class="my-6" />

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
