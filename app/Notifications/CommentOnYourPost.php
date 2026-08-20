<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

/**
 * Tells a post author that someone commented on their post. Only raised for commenters
 * who can see the post, so it never leaks a secret group.
 */
class CommentOnYourPost extends Notification
{
    use Queueable;

    public function __construct(
        public Comment $comment,
        public User $commenter,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('comment')) {
            return [];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        App::setLocale(($notifiable instanceof User ? $notifiable->locale : null) ?? config('app.fallback_locale'));

        return (new MailMessage)
            ->subject(__('notifications.comment.subject'))
            ->line(__('notifications.comment.body', ['name' => $this->commenter->display_name]))
            ->action(__('notifications.comment.action'), route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => 'comment',
            'params' => ['name' => $this->commenter->display_name],
            'url' => route('dashboard'),
            'actor_id' => $this->commenter->id,
            'comment_id' => $this->comment->id,
        ];
    }
}
