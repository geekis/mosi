<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Actions\Comments\AddComment;
use App\Actions\Mentions\NotifyMentions;
use App\Actions\Reactions\React;
use App\Actions\Reactions\Unreact;
use App\Enums\ReactionType;
use App\Models\Post;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Shared post-composer behaviour for the feed and group pages: the editor body, the
 * pending attachments, media routing, and Facebook-style reactions. The business logic
 * itself lives in Action classes; this trait only holds the Livewire UI state and the
 * thin glue that hands off to them.
 *
 * The page keeps its own {@see publish()} (the feed posts personally; a group post
 * carries a group_id and requires membership), but both funnel their uploads through
 * {@see attachMediaTo()} so the mime-routing stays in one place.
 */
trait InteractsWithPostComposer
{
    use WithFileUploads;

    public string $composer = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /**
     * Draft comment text, keyed by post id, so each post's inline comment box binds to
     * its own slot: wire:model="commentDrafts.{id}".
     *
     * @var array<int, string>
     */
    public array $commentDrafts = [];

    /**
     * The people behind a post's reactions, populated when the "who reacted" modal opens.
     *
     * @var array<int, array{name: string, emoji: string, label: string, url: ?string, avatar: ?string, initials: string}>
     */
    public array $postReactionList = [];

    /**
     * Drop one pending upload before publishing, then reindex so the blade keys stay
     * contiguous.
     */
    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    /**
     * Add, change, or toggle off the viewer's reaction on a post they can see. Reacting
     * with the type already selected removes it (Facebook-style); any other type
     * replaces it. The post id comes from the client, so visibility is re-checked here.
     */
    public function react(int $postId, string $type, React $react, Unreact $unreact): void
    {
        $post = Post::find($postId);
        $reaction = ReactionType::tryFrom($type);
        $viewer = Auth::user();

        if ($post === null || $reaction === null || ! $viewer->can('view', $post)) {
            return;
        }

        $existing = $post->reactions()->where('user_id', $viewer->id)->first();

        // Compare the raw stored value, not the cast enum: a legacy row from before the
        // palette changed would throw when cast, and must not break toggling.
        if ($existing !== null && $existing->getRawOriginal('type') === $reaction->value) {
            $unreact($viewer, $post);

            return;
        }

        $react($viewer, $post, $reaction);
    }

    /**
     * Load the roster of who reacted to a post (and with which emoji) and open the modal.
     * The post id comes from the client, so visibility is re-checked.
     */
    public function showReactions(int $postId): void
    {
        $post = Post::find($postId);
        $viewer = Auth::user();

        if ($post === null || ! $viewer->can('view', $post)) {
            return;
        }

        $this->postReactionList = $post->reactions()
            ->with('user.media')
            ->latest()
            ->get()
            ->map(function ($reaction): array {
                $type = ReactionType::tryFrom($reaction->getRawOriginal('type'));
                $user = $reaction->user;

                return [
                    'name' => $user?->display_name ?? '',
                    'emoji' => $type?->emoji() ?? '',
                    'label' => $type?->label() ?? '',
                    'url' => $user !== null && $user->external_id !== null ? route('people.show', $user) : null,
                    'avatar' => $user?->avatarUrl(),
                    'initials' => $user?->initials() ?? '',
                ];
            })
            ->all();

        Flux::modal('post-reactions')->show();
    }

    /**
     * Add a plain-text comment to a post the viewer can see. The draft comes from the
     * per-post slot in {@see $commentDrafts}; the id is re-checked for visibility.
     */
    public function addComment(int $postId, AddComment $addComment): void
    {
        $body = trim((string) ($this->commentDrafts[$postId] ?? ''));
        $post = Post::find($postId);
        $viewer = Auth::user();

        if ($post === null || $body === '' || ! $viewer->can('view', $post)) {
            return;
        }

        $addComment($viewer, $post, $body);

        unset($this->commentDrafts[$postId]);
    }

    /**
     * The composer accepts up to ten files at 50 MB each — the same ceiling as chat, so
     * short videos are usable. The PHP/Livewire upload limits must allow this too.
     */
    protected function validateAttachments(): void
    {
        $this->validate([
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:51200'],
        ]);
    }

    /**
     * Route each pending upload into the right media collection by its declared mime:
     * inline images, playable videos, everything else a downloadable file.
     */
    protected function attachMediaTo(Post $post): void
    {
        foreach ($this->attachments as $file) {
            $mime = (string) $file->getMimeType();

            $collection = match (true) {
                str_starts_with($mime, 'image/') => 'images',
                str_starts_with($mime, 'video/') => 'videos',
                default => 'attachments',
            };

            $post->addMedia($file->getRealPath())
                ->usingName($file->getClientOriginalName())
                ->usingFileName($file->hashName())
                ->toMediaCollection($collection);
        }
    }

    protected function resetComposer(): void
    {
        $this->composer = '';
        $this->attachments = [];
    }

    /**
     * The @mention data for a page, built from one query over active colleagues:
     *
     *  - `list`: {handle, name} pairs for the composer/comment autocomplete (serialised to
     *    the browser). The handle is the display name without spaces, e.g. "Egill Heinesen"
     *    → "EgillHeinesen", matching {@see NotifyMentions}.
     *  - `map`: a handle-keyed lookup (lower-cased) used server-side to turn a written
     *
     *    @handle into a profile link with a hover card. Only staff with an external_id have
     *    a profile page, so others get a null url and render without a link.
     *
     * @return array{list: list<array{handle: string, name: string}>, map: array<string, array{name: string, title: string, url: string|null}>}
     */
    protected function mentionData(): array
    {
        $users = User::query()->active()->orderBy('given_name')->get();

        $list = [];
        $map = [];

        foreach ($users as $user) {
            $handle = NotifyMentions::handleFor($user);

            if ($handle === '') {
                continue;
            }

            $list[] = ['handle' => $handle, 'name' => (string) $user->display_name];
            $map[mb_strtolower($handle)] = [
                'name' => (string) $user->display_name,
                'title' => (string) ($user->job_title ?? ''),
                'url' => $user->external_id !== null ? route('people.show', $user) : null,
            ];
        }

        return ['list' => $list, 'map' => $map];
    }
}
