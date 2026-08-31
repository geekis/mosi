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
            $this->dispatch('toast', text: __('You joined :group.', ['group' => $this->group->name]), variant: 'success');
        }
    }

    public function requestToJoin(RequestToJoinGroup $requestToJoin): void
    {
        if ($this->group->visibility === GroupVisibility::Closed) {
            $requestToJoin(Auth::user(), $this->group);
            $this->dispatch('toast', text: __('Your request to join has been sent.'), variant: 'success');
        }
    }

    public function leave(LeaveGroup $leave): void
    {
        // Department groups mirror the org chart; you cannot opt out of your own department.
        if ($this->group->type !== GroupType::Department) {
            $leave(Auth::user(), $this->group);
            $this->dispatch('toast', text: __('You left :group.', ['group' => $this->group->name]), variant: 'success');
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

        $this->dispatch('modal-show', name: 'edit-group');
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

        $this->dispatch('modal-close', name: 'edit-group');
        $this->dispatch('toast', text: __('Group updated.'), variant: 'success');
    }

    public function saveBanner(UpdateGroupBanner $updateGroupBanner): void
    {
        $this->authorize('update', $this->group);

        $this->validate(['bannerFile' => ['required', 'image', 'max:8192']]);

        $updateGroupBanner($this->group, $this->bannerFile);

        $this->bannerFile = null;
        $this->group->load('media');
        $this->dispatch('modal-close', name: 'edit-group');
        $this->dispatch('toast', text: __('Banner updated.'), variant: 'success');
    }

    public function saveAvatar(UpdateGroupAvatar $updateGroupAvatar): void
    {
        $this->authorize('update', $this->group);

        $this->validate(['avatarFile' => ['required', 'image', 'max:8192']]);

        $updateGroupAvatar($this->group, $this->avatarFile);

        $this->avatarFile = null;
        $this->group->load('media');
        $this->dispatch('modal-close', name: 'edit-group');
        $this->dispatch('toast', text: __('Profile image updated.'), variant: 'success');
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

        $this->dispatch('toast', text: __('Invitation sent to :name.', ['name' => $target->display_name]), variant: 'success');
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
            $this->dispatch('toast', text: __('Request approved.'), variant: 'success');
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
            $this->dispatch('toast', text: __('Request declined.'), variant: 'success');
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
        $this->dispatch('modal-close', name: 'composer');
        $this->dispatch('toast', text: __('Your post is published.'), variant: 'success');
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
    <x-ui.card class="overflow-hidden p-0">
        <x-group-banner :group="$group" height="h-32" />

        <div class="px-6 pb-6 pt-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        @if ($group->avatarUrl())
                            <img src="{{ $group->avatarUrl() }}" alt="" class="size-14 shrink-0 rounded-xl object-cover" />
                        @endif
                        <div class="min-w-0">
                            <x-ui.heading size="xl">{{ $group->name }}</x-ui.heading>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <x-ui.badge size="sm"><x-ui.icon name="user-group" variant="micro" class="size-3" /> {{ $group->type->label() }}</x-ui.badge>
                                <x-ui.badge size="sm" color="{{ $group->visibility === App\Enums\GroupVisibility::Open ? 'green' : ($group->visibility === App\Enums\GroupVisibility::Secret ? 'zinc' : 'gold') }}">
                                    {{ $group->visibility->label() }}
                                </x-ui.badge>
                                <x-ui.text size="sm">
                                    {{ __(':count members', ['count' => $memberCount]) }}
                                </x-ui.text>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if ($isMember)
                        <x-ui.button size="sm" variant="ghost" icon="chat-bubble-left-right" wire:click="openChat">
                            {{ __('Group chat') }}
                        </x-ui.button>
                    @endif

                    @if ($canInvite)
                        <x-ui.modal.trigger name="invite-members">
                            <x-ui.button size="sm" variant="ghost" icon="user-plus">{{ __('Invite') }}</x-ui.button>
                        </x-ui.modal.trigger>
                    @endif

                    @if ($canManage)
                        <x-ui.button size="sm" variant="ghost" icon="pencil-square" wire:click="openEdit">
                            {{ __('Edit group') }}
                        </x-ui.button>
                    @endif

                    @if ($isMember)
                        @if ($group->type !== App\Enums\GroupType::Department)
                            <x-ui.button size="sm" variant="ghost" icon="arrow-right-start-on-rectangle" wire:click="leave">
                                {{ __('Leave group') }}
                            </x-ui.button>
                        @endif
                    @elseif ($group->visibility === App\Enums\GroupVisibility::Open)
                        <x-ui.button size="sm" variant="primary" icon="user-plus" wire:click="join">
                            {{ __('Join group') }}
                        </x-ui.button>
                    @elseif ($group->visibility === App\Enums\GroupVisibility::Closed)
                        @if ($hasPendingRequest)
                            <x-ui.button size="sm" variant="ghost" icon="clock" disabled>
                                {{ __('Request pending') }}
                            </x-ui.button>
                        @else
                            <x-ui.button size="sm" variant="primary" icon="user-plus" wire:click="requestToJoin">
                                {{ __('Request to join') }}
                            </x-ui.button>
                        @endif
                    @endif
                </div>
            </div>

            @if ($group->description)
                <x-ui.text class="mt-4 whitespace-pre-line">{{ $group->description }}</x-ui.text>
            @endif
        </div>
    </x-ui.card>

    {{-- Edit modal (owners, moderators, comms, super-admins) --}}
    @if ($canManage)
        <x-ui.modal name="edit-group" class="max-w-lg">
            <div class="space-y-5">
                <x-ui.heading size="lg">{{ __('Edit group') }}</x-ui.heading>

                <div class="space-y-4">
                    <x-ui.input wire:model="name_is" :label="__('Name (Icelandic)')" />
                    <x-ui.input wire:model="name_en" :label="__('Name (English)')" />
                    <x-ui.textarea wire:model="description_is" :label="__('Description (Icelandic)')" rows="3" />
                    <x-ui.textarea wire:model="description_en" :label="__('Description (English)')" rows="3" />
                    <div class="flex justify-end">
                        <x-ui.button variant="primary" wire:click="saveGroup" data-test="save-group">{{ __('Save') }}</x-ui.button>
                    </div>
                </div>

                <x-ui.separator variant="subtle" />

                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        @if ($group->avatarUrl())
                            <img src="{{ $group->avatarUrl() }}" alt="" class="size-12 shrink-0 rounded-lg object-cover" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <x-ui.input type="file" wire:model="avatarFile" :label="__('Profile image')" accept="image/*" />
                        </div>
                    </div>
                    @if ($avatarFile)
                        <x-ui.button size="sm" variant="primary" wire:click="saveAvatar" data-test="save-group-avatar">{{ __('Save profile image') }}</x-ui.button>
                    @endif
                </div>

                <x-ui.separator variant="subtle" />

                <div class="space-y-2">
                    <x-ui.input type="file" wire:model="bannerFile" :label="__('Banner image')" accept="image/*" />
                    @if ($bannerFile)
                        <x-ui.button size="sm" variant="primary" wire:click="saveBanner" data-test="save-group-banner">{{ __('Save banner') }}</x-ui.button>
                    @endif
                </div>
            </div>
        </x-ui.modal>
    @endif

    @if ($canInvite)
        {{-- Invite colleagues: search by name, minus members and pending invitations. --}}
        <x-ui.modal name="invite-members" class="max-w-md">
            <div class="space-y-4">
                <x-ui.heading size="lg">{{ __('Invite') }}</x-ui.heading>
                <x-ui.input wire:model.live.debounce.300ms="inviteSearch" icon="magnifying-glass" :placeholder="__('Search people…')" autocomplete="off" />
                <div class="max-h-72 space-y-1 overflow-y-auto">
                    @forelse ($inviteCandidates as $candidate)
                        <div wire:key="candidate-{{ $candidate->id }}" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-sand-100 dark:hover:bg-zinc-800">
                            <x-avatar :user="$candidate" size="sm" />
                            <div class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $candidate->display_name }}</span>
                                @if ($candidate->job_title)
                                    <x-ui.text size="sm" class="truncate">{{ $candidate->job_title }}</x-ui.text>
                                @endif
                            </div>
                            <x-ui.button size="sm" variant="primary" icon="user-plus" wire:click="invite({{ $candidate->id }})">
                                {{ __('Invite') }}
                            </x-ui.button>
                        </div>
                    @empty
                        @if (trim($inviteSearch) !== '')
                            <x-ui.text size="sm">{{ __('No one matches your search.') }}</x-ui.text>
                        @endif
                    @endforelse
                </div>
            </div>
        </x-ui.modal>
    @endif

    {{-- Tabs at the bottom of the group header --}}
    <x-ui.tabs :selected="$tab" property="tab" class="mt-4">
        <x-ui.tab name="feed" icon="rss">{{ __('Feed') }}</x-ui.tab>
        <x-ui.tab name="events" icon="calendar-days">{{ __('Events') }}</x-ui.tab>
        <x-ui.tab name="media" icon="photo">{{ __('Media') }}</x-ui.tab>
        <x-ui.tab name="files" icon="document-text">{{ __('Files') }}</x-ui.tab>
        <x-ui.tab name="members" icon="user-group">{{ __('Members') }}</x-ui.tab>
    </x-ui.tabs>

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
                <x-ui.alert icon="lock-closed">{{ __('Members only — join to view.') }}</x-ui.alert>
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
                <x-ui.alert icon="rss">{{ __('No posts in this group yet.') }}</x-ui.alert>
            @endforelse

            @if ($posts->hasPages())
                <div>{{ $posts->links() }}</div>
            @endif
            </div>

            {{-- Right rail --}}
            <div class="space-y-4 md:col-span-1">
                @if ($isMember)
                    {{-- Upcoming events for this group --}}
                    <x-ui.card class="space-y-3">
                        <div class="flex items-center justify-between">
                            <x-ui.heading size="sm">{{ __('Upcoming events') }}</x-ui.heading>
                            <x-ui.button size="xs" variant="ghost" wire:click="$set('tab', 'events')">{{ __('See all') }}</x-ui.button>
                        </div>
                        @forelse ($sidebarEvents as $event)
                            <a wire:key="side-event-{{ $event->id }}" href="{{ route('events.show', $event) }}" wire:navigate
                                class="block rounded-lg p-1 hover:bg-sand-100 dark:hover:bg-zinc-700">
                                <div class="truncate text-sm font-medium">{{ $event->title }}</div>
                                <x-ui.text size="sm">
                                    {{ $event->starts_at->translatedFormat('j. M Y, H:i') }}
                                </x-ui.text>
                            </a>
                        @empty
                            <x-ui.text size="sm">{{ __('No upcoming events yet.') }}</x-ui.text>
                        @endforelse
                    </x-ui.card>

                    {{-- Latest files --}}
                    <x-ui.card class="space-y-3">
                        <div class="flex items-center justify-between">
                            <x-ui.heading size="sm">{{ __('Files') }}</x-ui.heading>
                            <x-ui.button size="xs" variant="ghost" wire:click="$set('tab', 'files')">{{ __('See all') }}</x-ui.button>
                        </div>
                        @forelse ($sidebarFiles as $file)
                            <a wire:key="side-file-{{ $file->id }}" href="{{ route('groups.attachments', [$group, $file]) }}" target="_blank" rel="noopener"
                                class="flex items-center gap-2 rounded-lg p-1 hover:bg-sand-100 dark:hover:bg-zinc-700">
                                <x-ui.icon name="document-text" class="size-5 shrink-0 text-mist-400" />
                                <span class="min-w-0 truncate text-sm">{{ $file->file_name }}</span>
                            </a>
                        @empty
                            <x-ui.text size="sm">{{ __('No files yet.') }}</x-ui.text>
                        @endforelse
                    </x-ui.card>

                    {{-- Latest images --}}
                    <x-ui.card class="space-y-3">
                        <div class="flex items-center justify-between">
                            <x-ui.heading size="sm">{{ __('Media') }}</x-ui.heading>
                            <x-ui.button size="xs" variant="ghost" wire:click="$set('tab', 'media')">{{ __('See all') }}</x-ui.button>
                        </div>
                        @if ($sidebarImages->isEmpty())
                            <x-ui.text size="sm">{{ __('No media yet.') }}</x-ui.text>
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
                    </x-ui.card>
                @endif

                {{-- Members preview: 10 random members + count, "More" opens the Members tab --}}
                <x-ui.card class="space-y-3">
                    <x-ui.heading size="sm">{{ __(':count members', ['count' => $memberCount]) }}</x-ui.heading>
                    <div class="grid grid-cols-5 gap-2">
                        @foreach ($randomMembers as $member)
                            <a wire:key="side-member-{{ $member->id }}" href="{{ route('people.show', $member) }}" wire:navigate
                                class="hover:opacity-80" title="{{ $member->display_name }}">
                                <x-avatar :user="$member" size="sm" />
                            </a>
                        @endforeach
                    </div>
                    <x-ui.button size="sm" variant="ghost" icon-trailing="arrow-right" wire:click="$set('tab', 'members')" class="w-full">
                        {{ __('More') }}
                    </x-ui.button>
                </x-ui.card>
            </div>
        </div>
    @endif

    {{-- Members --}}
    @if ($tab === 'members')
        <div class="mt-4 space-y-4">
            @if ($canManage && $pendingRequests->isNotEmpty())
                <x-ui.card class="space-y-3">
                    <x-ui.heading size="sm">{{ __('Join requests') }}</x-ui.heading>
                    @foreach ($pendingRequests as $request)
                        <div wire:key="request-{{ $request->id }}" class="flex items-center gap-2">
                            <x-avatar :user="$request->user" size="xs" />
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $request->user?->display_name }}</span>
                            <x-ui.button size="xs" variant="primary" icon="check" wire:click="approveRequest({{ $request->id }})" :aria-label="__('Accept')" />
                            <x-ui.button size="xs" variant="ghost" icon="x-mark" wire:click="declineRequest({{ $request->id }})" :aria-label="__('Decline')" />
                        </div>
                    @endforeach
                </x-ui.card>
            @endif

            <x-ui.card class="space-y-3">
                <x-ui.heading size="sm">{{ __(':count members', ['count' => $memberCount]) }}</x-ui.heading>
                <div class="divide-y divide-sand-100 dark:divide-zinc-700">
                    @foreach ($members as $member)
                        <div wire:key="member-{{ $member->id }}" class="flex items-center gap-3 py-2">
                            <a href="{{ route('people.show', $member) }}" wire:navigate class="shrink-0 hover:opacity-80">
                                <x-avatar :user="$member" size="sm" />
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('people.show', $member) }}" wire:navigate class="block truncate text-sm font-medium hover:underline">
                                    {{ $member->display_name }}
                                </a>
                                @if ($member->job_title)
                                    <x-ui.text size="sm" class="block truncate">{{ $member->job_title }}</x-ui.text>
                                @endif
                                <x-user-presence :user="$member" class="mt-0.5" />
                            </div>
                            @unless ($me->is($member))
                                <x-ui.button size="sm" variant="ghost" icon="chat-bubble-left-right" wire:click="message({{ $member->id }})">
                                    {{ __('Message') }}
                                </x-ui.button>
                            @endunless
                        </div>
                    @endforeach
                </div>
                @if ($memberCount > $members->count())
                    <x-ui.text size="sm">
                        {{ __('+:count more', ['count' => $memberCount - $members->count()]) }}
                    </x-ui.text>
                @endif
            </x-ui.card>
        </div>
    @endif

    <x-post-media-viewer />
    <x-post-reactions-modal :reactions="$postReactionList" />
</div>
