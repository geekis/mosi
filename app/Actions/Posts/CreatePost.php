<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Actions\Mentions\NotifyMentions;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * Create a post. A group post requires membership; the official flag is honoured only
 * for comms/super-admin. published_at defaults to now — pass null for a draft or a
 * future time to schedule. Media is attached by the caller after creation.
 *
 * The body is stored as plain text (trimmed) and escaped when rendered, so no HTML is
 * interpreted — the composer is a plain textarea, not a rich editor.
 */
final class CreatePost
{
    public function __construct(
        private readonly NotifyMentions $notifyMentions,
        private readonly NotifyGroupPost $notifyGroupPost,
    ) {}

    /**
     * @param  array{body: string, group_id?: int|null, is_official?: bool, published_at?: Carbon|string|null}  $data
     */
    public function __invoke(User $author, array $data): Post
    {
        $groupId = $data['group_id'] ?? null;

        if ($groupId !== null) {
            $group = Group::query()->findOrFail($groupId);

            if (! $group->hasMember($author)) {
                throw new DomainException('You must be a member of the group to post in it.');
            }
        }

        $body = trim($data['body']);

        $post = Post::create([
            'user_id' => $author->id,
            'group_id' => $groupId,
            'body' => $body,
            'is_official' => ($data['is_official'] ?? false) && $author->hasAnyRoleName('comms', 'super-admin'),
            'published_at' => array_key_exists('published_at', $data) ? $data['published_at'] : now(),
        ]);

        ($this->notifyMentions)($post, $author, $body);
        ($this->notifyGroupPost)($post, $author);

        return $post;
    }
}
