<?php

declare(strict_types=1);

namespace App\Actions\Gdpr;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

/**
 * Assemble a user's own personal data for a GDPR subject-access export. Deliberately
 * excludes the kennitala and anything derived from it — it must never appear in an export
 * (.ai/rules/staff-api.md).
 *
 * @phpstan-type Export array{profile: array<string, mixed>, groups: array<int, string>, posts: array<int, array<string, mixed>>, comments: array<int, array<string, mixed>>}
 */
final class ExportUserData
{
    /**
     * @return Export
     */
    public function __invoke(User $user): array
    {
        return [
            'profile' => [
                'external_id' => $user->external_id,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
                'organization_name' => $user->organization_name,
                'bio' => $user->bio,
                'locale' => $user->locale,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'groups' => $user->groups()->get()->map(fn ($group) => (string) $group->name)->values()->all(),
            'posts' => Post::query()
                ->where('user_id', $user->id)
                ->get()
                ->map(fn (Post $post): array => [
                    'body' => $post->body,
                    'created_at' => $post->created_at?->toIso8601String(),
                ])
                ->all(),
            'comments' => Comment::query()
                ->where('user_id', $user->id)
                ->get()
                ->map(fn (Comment $comment): array => [
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
