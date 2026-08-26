<?php

declare(strict_types=1);

namespace App\Actions\Mentions;

use App\Models\Mention;
use App\Models\User;
use App\Notifications\YouWereMentioned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Parse @mentions out of a plain-text body and notify each mentioned user — but only
 * those who can actually see the content, so a mention never leaks a secret group.
 *
 * A mention is written as the user's handle: their display name with the spaces removed,
 * e.g. "Egill Heinesen" is mentioned as "@EgillHeinesen". The composer's autocomplete
 * inserts exactly that, and matching is case-insensitive so a hand-typed handle still
 * resolves.
 */
final class NotifyMentions
{
    /**
     * @param  Collection<int, User>|null  $audience  The only people who may be mentioned.
     *                                                Defaults to every active colleague; chat
     *                                                passes the conversation's participants so a
     *                                                message can only mention people in the chat.
     */
    public function __invoke(Model $mentionable, User $author, string $body, ?Collection $audience = null): void
    {
        $handles = $this->extractHandles($body);

        if ($handles === []) {
            return;
        }

        $mentioned = ($audience ?? User::query()->active()->get())
            ->filter(fn (User $user): bool => in_array(mb_strtolower(self::handleFor($user)), $handles, true))
            ->reject(fn (User $user): bool => $user->is($author));

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
     * The handle for a user: their display name with all whitespace removed.
     */
    public static function handleFor(User $user): string
    {
        return preg_replace('/\s+/u', '', (string) $user->display_name) ?? '';
    }

    /**
     * Every distinct @handle token in the body, lower-cased for case-insensitive matching.
     * A handle is letters, numbers, and the marks a name might carry after spaces are
     * stripped — Unicode aware, so Icelandic þ/ð/æ/ö are kept.
     *
     * @return list<string>
     */
    private function extractHandles(string $body): array
    {
        preg_match_all('/@([\p{L}\p{N}._-]+)/u', $body, $matches);

        return array_values(array_unique(array_map('mb_strtolower', $matches[1])));
    }
}
