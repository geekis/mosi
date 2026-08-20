<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notification settings')] class extends Component {
    public bool $mention = true;

    public bool $comment = true;

    public bool $new_follower = true;

    public function mount(): void
    {
        $prefs = Auth::user()->notification_preferences ?? [];

        $this->mention = $prefs['mention'] ?? true;
        $this->comment = $prefs['comment'] ?? true;
        $this->new_follower = $prefs['new_follower'] ?? true;
    }

    public function save(): void
    {
        Auth::user()->update([
            'notification_preferences' => [
                'mention' => $this->mention,
                'comment' => $this->comment,
                'new_follower' => $this->new_follower,
            ],
        ]);

        Flux::toast(variant: 'success', text: __('Preferences saved.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Notifications')" :subheading="__('Choose what you want to be notified about')">
        <form wire:submit="save" class="my-6 space-y-6">
            <flux:switch wire:model="mention" :label="__('When someone mentions me')" />
            <flux:switch wire:model="comment" :label="__('When someone comments on my post')" />
            <flux:switch wire:model="new_follower" :label="__('When someone follows me')" />

            <flux:button type="submit" variant="primary" data-test="save-notifications-button">{{ __('Save') }}</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
