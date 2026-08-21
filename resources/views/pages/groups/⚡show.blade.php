<?php

use App\Actions\Groups\ApproveJoinRequest;
use App\Actions\Groups\DeclineJoinRequest;
use App\Actions\Groups\JoinGroup;
use App\Actions\Groups\LeaveGroup;
use App\Actions\Groups\RequestToJoinGroup;
use App\Actions\Groups\UpdateGroup;
use App\Actions\Groups\UpdateGroupBanner;
use App\Actions\Posts\CreatePost;
use App\Actions\Reactions\React;
use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Enums\ReactionType;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\Post;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Mosi')] class extends Component {
    use WithFileUploads, WithPagination;

    public Group $group;

    public string $composer = '';

    public string $name_is = '';

    public string $name_en = '';

    public string $description_is = '';

    public string $description_en = '';

    public ?TemporaryUploadedFile $bannerFile = null;

    public function mount(Group $group): void
    {
        abort_unless(Auth::user()->can('view', $group), 404);

        // Eager-load media so bannerUrl() does not trip the lazy-loading guard.
        $group->load('media');

        $this->group = $group;
    }

    public function join(JoinGroup $join): void
    {
        if ($this->group->visibility === GroupVisibility::Open) {
            $join(Auth::user(), $this->group);
            Flux::toast(text: __('You joined :group.', ['group' => $this->group->name]), variant: 'success');
        }
    }

    public function requestToJoin(RequestToJoinGroup $requestToJoin): void
    {
        if ($this->group->visibility === GroupVisibility::Closed) {
            $requestToJoin(Auth::user(), $this->group);
            Flux::toast(text: __('Your request to join has been sent.'), variant: 'success');
        }
    }

    public function leave(LeaveGroup $leave): void
    {
        // Department groups mirror the org chart; you cannot opt out of your own department.
        if ($this->group->type !== GroupType::Department) {
            $leave(Auth::user(), $this->group);
            Flux::toast(text: __('You left :group.', ['group' => $this->group->name]), variant: 'success');
        }
    }

    /**
     * Populate the edit form with the current translations before opening the modal.
     */
    public function openEdit(): void
    {
        $this->authorize('update', $this->group);

        $this->name_is = $this->group->getTranslation('name', 'is', false);
        $this->name_en = $this->group->getTranslation('name', 'en', false);
        $this->description_is = $this->group->getTranslation('description', 'is', false);
        $this->description_en = $this->group->getTranslation('description', 'en', false);

        Flux::modal('edit-group')->show();
    }

    public function saveGroup(UpdateGroup $updateGroup): void
    {
        $this->authorize('update', $this->group);

        $validated = $this->validate([
            'name_is' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_is' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
        ]);

        $updateGroup($this->group, [
            'name' => ['is' => $validated['name_is'], 'en' => $validated['name_en']],
            'description' => ['is' => $validated['description_is'] ?? '', 'en' => $validated['description_en'] ?? ''],
        ]);

        Flux::modal('edit-group')->close();
        Flux::toast(text: __('Group updated.'), variant: 'success');
    }

    public function saveBanner(UpdateGroupBanner $updateGroupBanner): void
    {
        $this->authorize('update', $this->group);

        $this->validate(['bannerFile' => ['required', 'image', 'max:8192']]);

        $updateGroupBanner($this->group, $this->bannerFile);

        $this->bannerFile = null;
        $this->group->load('media');
        Flux::modal('edit-group')->close();
        Flux::toast(text: __('Banner updated.'), variant: 'success');
    }

    public function approveRequest(int $requestId, ApproveJoinRequest $approve): void
    {
        $this->authorize('manageJoinRequests', $this->group);

        $request = GroupJoinRequest::query()
            ->where('group_id', $this->group->id)
            ->whereKey($requestId)
            ->first();

        if ($request !== null) {
            $approve($request);
            Flux::toast(text: __('Request approved.'), variant: 'success');
        }
    }

    public function declineRequest(int $requestId, DeclineJoinRequest $decline): void
    {
        $this->authorize('manageJoinRequests', $this->group);

        $request = GroupJoinRequest::query()
            ->where('group_id', $this->group->id)
            ->whereKey($requestId)
            ->first();

        if ($request !== null) {
            $decline($request);
            Flux::toast(text: __('Request declined.'), variant: 'success');
        }
    }

    public function publish(CreatePost $createPost): void
    {
        if (trim(strip_tags($this->composer)) === '' || ! $this->group->hasMember(Auth::user())) {
            return;
        }

        $createPost(Auth::user(), ['body' => $this->composer, 'group_id' => $this->group->id]);

        $this->composer = '';
        Flux::toast(text: __('Your post is published.'), variant: 'success');
    }

    public function react(int $postId, string $type, React $react): void
    {
        $post = Post::find($postId);
        $reaction = ReactionType::tryFrom($type);

        if ($post === null || $reaction === null || ! Auth::user()->can('view', $post)) {
            return;
        }

        $react(Auth::user(), $post, $reaction);
    }

    /**
     * @return array{isMember: bool, canManage: bool, hasPendingRequest: bool, pendingRequests: \Illuminate\Support\Collection<int, GroupJoinRequest>, memberCount: int, members: \Illuminate\Support\Collection<int, \App\Models\User>, posts: \Illuminate\Contracts\Pagination\CursorPaginator}
     */
    public function with(): array
    {
        $viewer = Auth::user();
        $canManage = $viewer->can('update', $this->group);

        return [
            'isMember' => $this->group->hasMember($viewer),
            'canManage' => $canManage,
            'hasPendingRequest' => GroupJoinRequest::query()
                ->where('group_id', $this->group->id)
                ->where('user_id', $viewer->id)
                ->exists(),
            'pendingRequests' => $canManage
                ? GroupJoinRequest::query()->where('group_id', $this->group->id)->with('user.media')->latest()->get()
                : collect(),
            'memberCount' => $this->group->members()->count(),
            'members' => $this->group->members()->orderBy('given_name')->limit(24)->get(),
            'posts' => Post::query()
                ->where('group_id', $this->group->id)
                ->published()
                ->with('author.media')
                ->withCount(['reactions', 'comments'])
                ->latest('published_at')
                ->latest('id')
                ->cursorPaginate(15),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-5xl">
    {{-- Group header --}}
    <flux:card class="overflow-hidden p-0">
        <div class="h-32 w-full bg-gradient-to-br from-primary-600 to-primary-800">
            @if ($group->bannerUrl())
                <img src="{{ $group->bannerUrl() }}" alt="" class="h-32 w-full object-cover" />
            @endif
        </div>

        <div class="px-6 pb-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <flux:heading size="xl" class="mt-4">{{ $group->name }}</flux:heading>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" icon="user-group">{{ $group->type->label() }}</flux:badge>
                        <flux:badge size="sm" color="{{ $group->visibility === App\Enums\GroupVisibility::Open ? 'green' : ($group->visibility === App\Enums\GroupVisibility::Secret ? 'zinc' : 'amber') }}">
                            {{ $group->visibility->label() }}
                        </flux:badge>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ __(':count members', ['count' => $memberCount]) }}
                        </flux:text>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    @if ($canManage)
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEdit">
                            {{ __('Edit group') }}
                        </flux:button>
                    @endif

                    @if ($isMember)
                        @if ($group->type !== App\Enums\GroupType::Department)
                            <flux:button size="sm" variant="ghost" icon="arrow-right-start-on-rectangle" wire:click="leave">
                                {{ __('Leave group') }}
                            </flux:button>
                        @endif
                    @elseif ($group->visibility === App\Enums\GroupVisibility::Open)
                        <flux:button size="sm" variant="primary" icon="user-plus" wire:click="join">
                            {{ __('Join group') }}
                        </flux:button>
                    @elseif ($group->visibility === App\Enums\GroupVisibility::Closed)
                        @if ($hasPendingRequest)
                            <flux:button size="sm" variant="ghost" icon="clock" disabled>
                                {{ __('Request pending') }}
                            </flux:button>
                        @else
                            <flux:button size="sm" variant="primary" icon="user-plus" wire:click="requestToJoin">
                                {{ __('Request to join') }}
                            </flux:button>
                        @endif
                    @endif
                </div>
            </div>

            @if ($group->description)
                <flux:text class="mt-4 whitespace-pre-line">{{ $group->description }}</flux:text>
            @endif
        </div>
    </flux:card>

    {{-- Edit modal (owners, moderators, comms, super-admins) --}}
    @if ($canManage)
        <flux:modal name="edit-group" class="w-full max-w-lg">
            <div class="space-y-5">
                <flux:heading size="lg">{{ __('Edit group') }}</flux:heading>

                <div class="space-y-4">
                    <flux:input wire:model="name_is" :label="__('Name (Icelandic)')" />
                    <flux:input wire:model="name_en" :label="__('Name (English)')" />
                    <flux:textarea wire:model="description_is" :label="__('Description (Icelandic)')" rows="3" />
                    <flux:textarea wire:model="description_en" :label="__('Description (English)')" rows="3" />
                    <div class="flex justify-end">
                        <flux:button variant="primary" wire:click="saveGroup" data-test="save-group">{{ __('Save') }}</flux:button>
                    </div>
                </div>

                <flux:separator variant="subtle" />

                <div class="space-y-2">
                    <flux:input type="file" wire:model="bannerFile" :label="__('Banner image')" accept="image/*" />
                    <flux:error name="bannerFile" />
                    @if ($bannerFile)
                        <flux:button size="sm" variant="primary" wire:click="saveBanner" data-test="save-group-banner">{{ __('Save banner') }}</flux:button>
                    @endif
                </div>
            </div>
        </flux:modal>
    @endif

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        {{-- Group feed --}}
        <div class="space-y-4 md:col-span-2">
            @if ($isMember)
                <flux:card class="space-y-3">
                    <flux:editor wire:model="composer" :placeholder="__('Share something with :group…', ['group' => $group->name])" />
                    <div class="flex justify-end">
                        <flux:button variant="primary" wire:click="publish" data-test="group-publish">{{ __('Post') }}</flux:button>
                    </div>
                </flux:card>
            @endif

            @forelse ($posts as $post)
                <flux:card wire:key="post-{{ $post->id }}" @class(['ring-1 ring-primary-500/40' => $post->is_official])>
                    <div class="flex items-center gap-3">
                        <flux:avatar :name="$post->author?->display_name" :src="$post->author?->avatarUrl()" size="sm" />
                        <div class="min-w-0">
                            @if ($post->author?->external_id)
                                <a href="{{ route('people.show', $post->author) }}" class="hover:underline" wire:navigate>
                                    <flux:heading class="truncate">{{ $post->author->display_name }}</flux:heading>
                                </a>
                            @else
                                <flux:heading class="truncate">{{ $post->author?->display_name }}</flux:heading>
                            @endif
                            <flux:text size="sm" class="flex items-center gap-1 truncate">
                                <span>{{ $post->published_at?->diffForHumans() }}</span>
                                @if ($post->is_official)
                                    <flux:badge size="sm" color="green">{{ __('Official') }}</flux:badge>
                                @endif
                            </flux:text>
                        </div>
                    </div>

                    <div class="prose prose-sm mt-3 max-w-none whitespace-pre-line dark:prose-invert">{!! $post->body !!}</div>

                    <flux:separator class="my-3" variant="subtle" />

                    <div class="flex items-center gap-2 text-sm">
                        <flux:button size="xs" variant="ghost" icon="hand-thumb-up" wire:click="react({{ $post->id }}, 'like')">
                            {{ $post->reactions_count }}
                        </flux:button>
                        <flux:button size="xs" variant="ghost" icon="chat-bubble-oval-left-ellipsis">
                            {{ $post->comments_count }}
                        </flux:button>
                    </div>
                </flux:card>
            @empty
                <flux:callout icon="rss">{{ __('No posts in this group yet.') }}</flux:callout>
            @endforelse

            @if ($posts->hasPages())
                <div>{{ $posts->links() }}</div>
            @endif
        </div>

        {{-- Sidebar: pending requests (managers) + members --}}
        <div class="space-y-4 md:col-span-1">
            @if ($canManage && $pendingRequests->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __('Join requests') }}</flux:heading>
                    @foreach ($pendingRequests as $request)
                        <div wire:key="request-{{ $request->id }}" class="flex items-center gap-2">
                            <flux:avatar :name="$request->user?->display_name" :initials="$request->user?->initials()" :src="$request->user?->avatarUrl()" size="xs" />
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $request->user?->display_name }}</span>
                            <flux:button size="xs" variant="primary" icon="check" wire:click="approveRequest({{ $request->id }})" />
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="declineRequest({{ $request->id }})" />
                        </div>
                    @endforeach
                </flux:card>
            @endif

            <flux:card class="space-y-3 self-start">
                <flux:heading size="sm">{{ __('Members') }}</flux:heading>
                <div class="grid grid-cols-6 gap-2 md:grid-cols-4">
                    @foreach ($members as $member)
                        <a wire:key="member-{{ $member->id }}" href="{{ route('people.show', $member) }}" wire:navigate
                            class="hover:opacity-80" title="{{ $member->display_name }}">
                            <flux:avatar :name="$member->display_name" :initials="$member->initials()" :src="$member->avatarUrl()" size="sm" />
                        </a>
                    @endforeach
                </div>
                @if ($memberCount > $members->count())
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        {{ __('+:count more', ['count' => $memberCount - $members->count()]) }}
                    </flux:text>
                @endif
            </flux:card>
        </div>
    </div>
</div>
