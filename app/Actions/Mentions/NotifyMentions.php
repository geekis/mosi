<?php

declare(strict_types=1);

namespace App\Actions\Mentions;

use App\Models\Mention;
use App\Models\User;
use App\Notifications\YouWereMentioned;
use Illuminate\Database\Eloquent\Model;

/**
 * Parse @mentions out of a body and notify each mentioned user — but only those who can
 * actually see the content, so a mention never leaks a secret group. The composer embeds
 * mentions as data-mention="{userId}" (the feed/composer UI lands in a later phase).
 */
final class NotifyMentions
{
    public function __invoke(Model $mentionable, User $author, string $body): void
    {
        $ids = $this->extractUserIds($body);

        if ($ids === []) {
            return;
        }

        $mentioned = User::query()
            ->whereIn('id', $ids)
            ->whereKeyNot($author->id)
            ->get();

        foreach ($mentioned as $user) {
            if (! $user->can('view', $mentionable)) {
                continue; // do not leak content the mentioned user may not see
            }

            Mention::firstOrCreate([
                'mentionable_id' => $mentionable->getKey(),
                'mentionable_type' => $mentionable->getMorphClass(),
                'user_id' => $user->id,
            ]);

            $user->notify(new YouWereMentioned($mentionable, $author));
        }
    }

    /**
     * @return list<int>
     */
    private function extractUserIds(string $body): array
    {
        preg_match_all('/data-mention=["\'](\d+)["\']/', $body, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }
}
