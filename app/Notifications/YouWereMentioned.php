<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

/**
 * Tells a user they were @mentioned in a post or comment. Only sent to users who can
 * see the content (filtered in NotifyMentions), so it never leaks a secret group.
 */
class YouWereMentioned extends Notification
{
    use Queueable;

    public function __construct(
        public Model $mentionable,
        public User $author,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('mention')) {
            return [];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Render in the recipient's locale, not the queue worker's (localisation rule).
        App::setLocale(($notifiable instanceof User ? $notifiable->locale : null) ?? config('app.fallback_locale'));

        return (new MailMessage)
            ->subject(__('notifications.mention.subject'))
            ->line(__('notifications.mention.body', ['name' => $this->author->display_name]))
            ->action(__('notifications.mention.action'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => 'mention',
            'params' => ['name' => $this->author->display_name],
            'url' => $this->url(),
            'actor_id' => $this->author->id,
            'mentionable_type' => $this->mentionable->getMorphClass(),
            'mentionable_id' => $this->mentionable->getKey(),
        ];
    }

    /**
     * Where the notification points: a chat mention opens the conversation, everything
     * else (post, comment) lands on the dashboard where its feed item lives.
     */
    private function url(): string
    {
        if ($this->mentionable instanceof Message) {
            return route('chat', ['conversation' => $this->mentionable->conversation_id]);
        }

        return route('dashboard');
    }
}
