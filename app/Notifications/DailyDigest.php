<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Localization\PluralChoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

/**
 * A daily summary email of unread activity. Rendered in the recipient's locale — the
 * common queued-job localisation trap (.ai/rules/localisation.md) — and pluralised with
 * the Icelandic-aware helper, since trans_choice gets Icelandic counts wrong.
 */
class DailyDigest extends Notification
{
    use Queueable;

    public function __construct(public int $unreadCount) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = ($notifiable instanceof User ? $notifiable->locale : null) ?? config('app.fallback_locale');
        App::setLocale($locale);

        $name = $notifiable instanceof User ? ($notifiable->given_name ?? $notifiable->display_name) : '';

        [$singular, $plural] = array_pad(explode('|', __('notifications.digest.unread')), 2, '');

        return (new MailMessage)
            ->subject(__('notifications.digest.subject'))
            ->greeting(__('notifications.digest.greeting', ['name' => $name]))
            ->line(__('notifications.digest.intro'))
            ->line(PluralChoice::make($this->unreadCount, $singular, $plural, $locale))
            ->action(__('notifications.digest.action'), route('notifications'));
    }
}
