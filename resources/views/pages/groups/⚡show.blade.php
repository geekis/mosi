<?php

use App\Actions\Chat\EnsureGroupConversation;
use App\Actions\Chat\StartDirectConversation;
use App\Actions\Groups\ApproveJoinRequest;
use App\Actions\Groups\DeclineJoinRequest;
use App\Actions\Groups\JoinGroup;
use App\Actions\Groups\LeaveGroup;
use App\Actions\Groups\InviteToGroup;
use App\Actions\Groups\MarkGroupNotificationsRead;
use App\Actions\Groups\RequestToJoinGroup;
use App\Actions\Groups\UpdateGroup;
use App\Actions\Groups\UpdateGroupAvatar;
use App\Actions\Groups\UpdateGroupBanner;
use App\Actions\Posts\CreatePost;
use App\Concerns\InteractsWithPostComposer;
use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupJoinRequest;
use App\Models\Post;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithPagination;

new #[Title('Mosi')] class extends Component {
    use InteractsWithPostComposer, WithPagination;

    public Group $group;

    /** Active tab: feed | events | media | files | members. */
    #[Url(as: 'tab', except: 'feed')]
    public string $tab = 'feed';

    public string $name_is = '';

    public string $name_en = '';

    public string $description_is = '';

    public string $description_en = '';

    public ?TemporaryUploadedFile $bannerFile = null;

    public ?TemporaryUploadedFile $avatarFile = null;

    public string $inviteSearch = '';

    public function mount(Group $group, MarkGroupNotificationsRead $markRead): void
    {
        abort_unless(Auth::user()->can('view', $group), 404);

        // Eager-load media so bannerUrl() does not trip the lazy-loading guard.
        $group->load('media');

        $this->group = $group;

        // Opening the group clears its unread-post badge (and the matching bell items).
        $markRead(Auth::user(), $group);
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
     * Open the group's dedicated chat, creating it and syncing members on first use.
     * Members only — the chat mirrors the group's membership.
     */
    public function openChat(EnsureGroupConversation $ensureGroupConversation): void
    {
        if (! $this->group->hasMember(Auth::user())) {
            return;
        }

        $conversation = $ensureGroupConversation($this->group);

        $this->redirect(route('chat', ['conversation' => $conversation->id]), navigate: true);
    }

    /**
     * Start (or reopen) a one-to-one chat with another member of this group.
     */
    public function message(int $userId, StartDirectConversation $startDirectConversation): void
    {
        $target = $this->group->members()->whereKey($userId)->first();

        if ($target === null || Auth::user()->is($target)) {
            return;
        }

        $conversation = $startDirectConversation(Auth::user(), $target);

        $this->redirect(route('chat', ['conversation' => $conversation->id]), navigate: true);
    }

    /**
     * Populate the edit form with the current translations before opening the modal.
     */
    public function openEdit(): void
    {
        $this->authorize('update', $this->group);

        $this->name_is = (string) $this->group->getTranslation('name', 'is', false);
        $this->name_en = (string) $this->group->getTranslation('name', 'en', false);
        $this->description_is = (string) $this->group->getTranslation('description', 'is', false);
        $this->description_en = (string) $this->group->getTranslation('description', 'en', false);

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

    public function saveAvatar(UpdateGroupAvatar $updateGroupAvatar): void
    {
        $this->authorize('update', $this->group);

        $this->validate(['avatarFile' => ['required', 'image', 'max:8192']]);

        $updateGroupAvatar($this->group, $this->avatarFile);

        $this->avatarFile = null;
        $this->group->load('media');
        Flux::modal('edit-group')->close();
        Flux::toast(text: __('Profile image updated.'), variant: 'success');
    }

    /**
     * Invite a colleague into the group. Gated by the `invite` policy ability — this is
     * the only path into a secret group, so it stays with owners/moderators.
     */
    public function invite(int $userId, InviteToGroup $inviteToGroup): void
    {
        $this->authorize('invite', $this->group);

        $target = User::query()->active()->whereKey($userId)->first();

        if ($target === null || $this->group->hasMember($target)) {
            return;
        }

        $inviteToGroup(Auth::user(), $this->group, $target);

        Flux::toast(text: __('Invitation sent to :name.', ['name' => $target->display_name]), variant: 'success');
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
        if (! $this->group->hasMember(Auth::user())) {
            return;
        }

        if (trim(strip_tags($this->composer)) === '' && $this->attachments === []) {
            return;
        }

        $this->validateAttachments();

        $post = $createPost(Auth::user(), ['body' => $this->composer, 'group_id' => $this->group->id]);
        $this->attachMediaTo($post);

        $this->resetComposer();
        Flux::modal('composer')->close();
        Flux::toast(text: __('Your post is published.'), variant: 'success');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $viewer = Auth::user();
        $canManage = $viewer->can('update', $this->group);
        $isMember = $this->group->hasMember($viewer);

        $canInvite = $viewer->can('invite', $this->group);

        $data = [
            'me' => $viewer,
            'isMember' => $isMember,
            'canManage' => $canManage,
            'canInvite' => $canInvite,
            'hasPendingRequest' => GroupJoinRequest::query()
                ->where('group_id', $this->group->id)
                ->where('user_id', $viewer->id)
                ->exists(),
            'memberCount' => $this->group->members()->count(),
            // Colleagues matching the invite search, minus current members and anyone
            // already holding a pending invitation. Sorted by given_name — patronymics
            // are not surnames (.ai/rules/localisation.md).
            'inviteCandidates' => $canInvite && trim($this->inviteSearch) !== ''
                ? User::query()
                    ->active()
                    ->where('display_name', 'like', '%'.trim($this->inviteSearch).'%')
                    ->whereNotIn('id', $this->group->members()->select('users.id'))
                    ->whereNotIn('id', GroupInvitation::query()->where('group_id', $this->group->id)->select('user_id'))
                    ->with('media')
                    ->orderBy('given_name')
                    ->limit(8)
                    ->get()
                : collect(),
        ];

        if ($this->tab === 'feed') {
            $mentions = $this->mentionData();
            $data['mentionables'] = $mentions['list'];
            $data['mentionMap'] = $mentions['map'];
            $data['posts'] = Post::query()
                ->where('group_id', $this->group->id)
                ->published()
                ->with([
                    'author.media', 'media', 'reactions',
                    'comments' => fn ($query) => $query->with('author.media')->oldest(),
                ])
                ->withCount(['reactions', 'comments'])
                ->latest('published_at')
                ->latest('id')
                ->cursorPaginate(15);

            // Right rail. The events/files/media widgets are members-only content, so they
            // only load for members; the members widget is a public preview.
            $data['randomMembers'] = $this->group->members()->with('media')->inRandomOrder()->limit(10)->get();
            $data['sidebarEvents'] = $isMember
                ? Event::query()->where('group_id', $this->group->id)->upcoming()->limit(5)->get()
                : collect();
            $data['sidebarFiles'] = $isMember
                ? $this->group->mediaIn('files')->sortByDesc('created_at')->take(5)->values()
                : collect();
            $data['sidebarImages'] = $isMember
                ? $this->group->mediaIn('gallery')
                    ->filter(fn ($m): bool => str_starts_with((string) $m->mime_type, 'image/'))
                    ->sortByDesc('created_at')->take(6)->values()
                : collect();
        }

        if ($this->tab === 'members') {
            $data['pendingRequests'] = $canManage
                ? GroupJoinRequest::query()->where('group_id', $this->group->id)->with('user.media')->latest()->get()
                : collect();
            $data['members'] = $this->group->members()->with('media')->orderBy('given_name')->limit(60)->get();
        }

        return $data;
    }
}; ?>

<div class="mx-auto w-full max-w-5xl"
    x-data="{
        viewer: null,
        open(kind, url, name) { this.viewer = { kind, url, name }; },
        close() { this.viewer = null; },
    }"
    x-on:keydown.escape.window="close()">
    {{-- Group header --}}
    <flux:card class="overflow-hidden p-0">
        <x-group-banner :group="$group" height="h-32" />

        <div class="px-6 pb-6 pt-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        @if ($group->avatarUrl())
                            <img src="{{ $group->avatarUrl() }}" alt="" class="size-14 shrink-0 rounded-xl object-cover" />
                        @endif
                        <div class="min-w-0">
                            <flux:heading size="xl">{{ $group->name }}</flux:heading>
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
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if ($isMember)
                        <flux:button size="sm" variant="ghost" icon="chat-bubble-left-right" wire:click="openChat">
                            {{ __('Group chat') }}
                        </flux:button>
                    @endif

                    @if ($canInvite)
                        <flux:modal.trigger name="invite-members">
                            <flux:button size="sm" variant="ghost" icon="user-plus">{{ __('Invite') }}</flux:button>
                        </flux:modal.trigger>
                    @endif

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
                    <div class="flex items-center gap-3">
                        @if ($group->avatarUrl())
                            <img src="{{ $group->avatarUrl() }}" alt="" class="size-12 shrink-0 rounded-lg object-cover" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <flux:input type="file" wire:model="avatarFile" :label="__('Profile image')" accept="image/*" />
                            <flux:error name="avatarFile" />
                        </div>
                    </div>
                    @if ($avatarFile)
                        <flux:button size="sm" variant="primary" wire:click="saveAvatar" data-test="save-group-avatar">{{ __('Save profile image') }}</flux:button>
                    @endif
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

    @if ($canInvite)
        {{-- Invite colleagues: search by name, minus members and pending invitations. --}}
        <flux:modal name="invite-members" class="w-full max-w-md">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Invite') }}</flux:heading>
                <flux:input wire:model.live.debounce.300ms="inviteSearch" icon="magnifying-glass" :placeholder="__('Search people…')" autocomplete="off" />
                <div class="max-h-72 space-y-1 overflow-y-auto">
                    @forelse ($inviteCandidates as $candidate)
                        <div wire:key="candidate-{{ $candidate->id }}" class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <flux:avatar :name="$candidate->display_name" :src="$candidate->avatarUrl()" size="sm" />
                            <div class="min-w-0 flex-1">
                                <flux:text class="truncate font-medium">{{ $candidate->display_name }}</flux:text>
                                @if ($candidate->job_title)
                                    <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400">{{ $candidate->job_title }}</flux:text>
                                @endif
                            </div>
                            <flux:button size="sm" variant="primary" icon="user-plus" wire:click="invite({{ $candidate->id }})">
                                {{ __('Invite') }}
                            </flux:button>
                        </div>
                    @empty
                        @if (trim($inviteSearch) !== '')
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No one matches your search.') }}</flux:text>
                        @endif
                    @endforelse
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Tabs at the bottom of the group header --}}
    <flux:tabs wire:model.live="tab" variant="segmented" class="mt-4">
        <flux:tab name="feed" icon="rss">{{ __('Feed') }}</flux:tab>
        <flux:tab name="events" icon="calendar-days">{{ __('Events') }}</flux:tab>
        <flux:tab name="media" icon="photo">{{ __('Media') }}</flux:tab>
        <flux:tab name="files" icon="document-text">{{ __('Files') }}</flux:tab>
        <flux:tab name="members" icon="user-group">{{ __('Members') }}</flux:tab>
    </flux:tabs>

    {{-- Events / Media / Files are members-only. The child components only mount for
         members; the policy and streaming route are the real security boundary. --}}
    @if (in_array($tab, ['events', 'media', 'files'], true))
        <div class="mt-4">
            @if ($isMember)
                @if ($tab === 'events')
                    <livewire:pages::groups.events-tab :group="$group" wire:key="events-{{ $group->id }}" />
                @elseif ($tab === 'media')
                    <livewire:pages::groups.media-tab :group="$group" wire:key="media-{{ $group->id }}" />
                @else
                    <livewire:pages::groups.files-tab :group="$group" wire:key="files-{{ $group->id }}" />
                @endif
            @else
                <flux:callout icon="lock-closed">{{ __('Members only — join to view.') }}</flux:callout>
            @endif
        </div>
    @endif

    {{-- Feed --}}
    @if ($tab === 'feed')
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="space-y-4 md:col-span-2">
            @if ($isMember)
                <x-post-composer :me="$me"
                    :placeholder="__('Share something with :group…', ['group' => $group->name])"
                    :attachments="$attachments" :mentionables="$mentionables" post-test="group-publish" />
            @endif

            @forelse ($posts as $post)
                <x-post-card :post="$post" :show-group="false" :mentionables="$mentionables" :mention-map="$mentionMap" wire:key="post-{{ $post->id }}" />
            @empty
                <flux:callout icon="rss">{{ __('No posts in this group yet.') }}</flux:callout>
            @endforelse

            @if ($posts->hasPages())
                <div>{{ $posts->links() }}</div>
            @endif
            </div>

            {{-- Right rail --}}
            <div class="space-y-4 md:col-span-1">
                @if ($isMember)
                    {{-- Upcoming events for this group --}}
                    <flux:card class="space-y-3">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Upcoming events') }}</flux:heading>
                            <flux:button size="xs" variant="ghost" wire:click="$set('tab', 'events')">{{ __('See all') }}</flux:button>
                        </div>
                        @forelse ($sidebarEvents as $event)
                            <a wire:key="side-event-{{ $event->id }}" href="{{ route('events.show', $event) }}" wire:navigate
                                class="block rounded-lg p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                <div class="truncate text-sm font-medium">{{ $event->title }}</div>
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                    {{ $event->starts_at->translatedFormat('j. M Y, H:i') }}
                                </flux:text>
                            </a>
                        @empty
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No upcoming events yet.') }}</flux:text>
                        @endforelse
                    </flux:card>

                    {{-- Latest files --}}
                    <flux:card class="space-y-3">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Files') }}</flux:heading>
                            <flux:button size="xs" variant="ghost" wire:click="$set('tab', 'files')">{{ __('See all') }}</flux:button>
                        </div>
                        @forelse ($sidebarFiles as $file)
                            <a wire:key="side-file-{{ $file->id }}" href="{{ route('groups.attachments', [$group, $file]) }}" target="_blank" rel="noopener"
                                class="flex items-center gap-2 rounded-lg p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                <flux:icon name="document-text" class="size-5 shrink-0 text-zinc-400" />
                                <span class="min-w-0 truncate text-sm">{{ $file->file_name }}</span>
                            </a>
                        @empty
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No files yet.') }}</flux:text>
                        @endforelse
                    </flux:card>

                    {{-- Latest images --}}
                    <flux:card class="space-y-3">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Media') }}</flux:heading>
                            <flux:button size="xs" variant="ghost" wire:click="$set('tab', 'media')">{{ __('See all') }}</flux:button>
                        </div>
                        @if ($sidebarImages->isEmpty())
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('No media yet.') }}</flux:text>
                        @else
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($sidebarImages as $image)
                                    <button wire:key="side-image-{{ $image->id }}" type="button" wire:click="$set('tab', 'media')" class="overflow-hidden rounded-lg">
                                        <img src="{{ route('groups.attachments', [$group, $image]) }}" alt="{{ $image->file_name }}"
                                            class="aspect-square w-full object-cover hover:opacity-80" loading="lazy" />
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endif

                {{-- Members preview: 10 random members + count, "More" opens the Members tab --}}
                <flux:card class="space-y-3">
                    <flux:heading size="sm">{{ __(':count members', ['count' => $memberCount]) }}</flux:heading>
                    <div class="grid grid-cols-5 gap-2">
                        @foreach ($randomMembers as $member)
                            <a wire:key="side-member-{{ $member->id }}" href="{{ route('people.show', $member) }}" wire:navigate
                                class="hover:opacity-80" title="{{ $member->display_name }}">
                                <flux:avatar :name="$member->display_name" :initials="$member->initials()" :src="$member->avatarUrl()" size="sm" />
                            </a>
                        @endforeach
                    </div>
                    <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" wire:click="$set('tab', 'members')" class="w-full">
                        {{ __('More') }}
                    </flux:button>
                </flux:card>
            </div>
        </div>
    @endif

    {{-- Members --}}
    @if ($tab === 'members')
        <div class="mt-4 space-y-4">
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

            <flux:card class="space-y-3">
                <flux:heading size="sm">{{ __(':count members', ['count' => $memberCount]) }}</flux:heading>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach ($members as $member)
                        <div wire:key="member-{{ $member->id }}" class="flex items-center gap-3 py-2">
                            <a href="{{ route('people.show', $member) }}" wire:navigate class="shrink-0 hover:opacity-80">
                                <flux:avatar :name="$member->display_name" :initials="$member->initials()" :src="$member->avatarUrl()" size="sm" />
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('people.show', $member) }}" wire:navigate class="block truncate text-sm font-medium hover:underline">
                                    {{ $member->display_name }}
                                </a>
                                @if ($member->job_title)
                                    <flux:text size="sm" class="block truncate text-zinc-500 dark:text-zinc-400">{{ $member->job_title }}</flux:text>
                                @endif
                                <x-user-presence :user="$member" class="mt-0.5" />
                            </div>
                            @unless ($me->is($member))
                                <flux:button size="sm" variant="ghost" icon="chat-bubble-left-right" wire:click="message({{ $member->id }})">
                                    {{ __('Message') }}
                                </flux:button>
                            @endunless
                        </div>
                    @endforeach
                </div>
                @if ($memberCount > $members->count())
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        {{ __('+:count more', ['count' => $memberCount - $members->count()]) }}
                    </flux:text>
                @endif
            </flux:card>
        </div>
    @endif

    <x-post-media-viewer />
    <x-post-reactions-modal :reactions="$postReactionList" />
</div>
