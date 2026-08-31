<?php

use App\Actions\Feed\BuildFeed;
use App\Actions\Feed\BuildLatest;
use App\Actions\Posts\CreatePost;
use App\Concerns\InteractsWithPostComposer;
use App\Models\Event;
use App\Models\Post;
use App\Models\Shortcut;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mosi')] class extends Component {
    use InteractsWithPostComposer;

    public string $tab = 'for-you';

    /**
     * Publish the composed post with any attachments. The action sanitises the HTML and
     * notifies mentions; media is routed into its collections afterwards.
     */
    public function publish(CreatePost $createPost): void
    {
        if (trim(strip_tags($this->composer)) === '' && $this->attachments === []) {
            return;
        }

        $this->validateAttachments();

        $post = $createPost(Auth::user(), ['body' => $this->composer]);
        $this->attachMediaTo($post);

        $this->resetComposer();
        $this->dispatch('modal-close', name: 'composer');
        $this->dispatch('toast', text: __('Your post is published.'), variant: 'success');
    }

    /**
     * @return array{me: User, posts: \Illuminate\Contracts\Pagination\CursorPaginator, myGroups: \Illuminate\Support\Collection<int, \App\Models\Group>, shortcuts: \Illuminate\Support\Collection<int, Shortcut>, upcomingBirthdays: \Illuminate\Support\Collection<int, User>}
     */
    public function with(BuildFeed $buildFeed, BuildLatest $buildLatest): array
    {
        $user = Auth::user();
        $mentions = $this->mentionData();

        // Order everyone with a known birthday by how soon it next falls: the arithmetic
        // month*100+day wraps the year cleanly and puts today's birthdays first. Portable
        // across SQLite and MySQL.
        $todayMonthDay = now()->month * 100 + now()->day;

        return [
            'me' => $user,
            'mentionMap' => $mentions['map'],
            'posts' => $this->tab === 'latest'
                ? $buildLatest($user, 15)
                : $buildFeed($user, 15),
            'myGroups' => $user->groups()->with('media')->orderBy('id')->get(),
            'mentionables' => $mentions['list'],
            'followersCount' => $user->followers()->count(),
            'followingCount' => $user->following()->count(),
            'postsCount' => Post::where('user_id', $user->id)->count(),
            'upcomingEvents' => Event::query()->visibleTo($user)->upcoming()->limit(3)->get(),
            'shortcuts' => Shortcut::query()->active()->get(),
            'upcomingBirthdays' => User::query()
                ->active()
                ->whereNotNull('birth_month')
                ->whereNotNull('birth_day')
                ->with('media')
                ->orderByRaw('CASE WHEN (birth_month * 100 + birth_day) >= ? THEN 0 ELSE 1 END', [$todayMonthDay])
                ->orderByRaw('birth_month * 100 + birth_day')
                ->limit(4)
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl gap-6"
    x-data="{
        viewer: null,
        open(kind, url, name) { this.viewer = { kind, url, name }; },
        close() { this.viewer = null; },
    }"
    x-on:keydown.escape.window="close()">
    {{-- Left rail: who you are, your groups, quick links. Appears at lg. --}}
    <aside class="hidden w-72 shrink-0 space-y-4 lg:block">
        <x-ui.card class="overflow-hidden p-0">
            {{-- Banner --}}
            <div class="h-20 w-full bg-gradient-to-br from-brand-600 to-brand-800">
                @if ($me->bannerUrl())
                    <img src="{{ $me->bannerUrl() }}" alt="" class="h-20 w-full object-cover" />
                @endif
            </div>

            <div class="px-4 pb-4 text-center">
                {{-- Avatar overlapping the banner --}}
                <div class="-mt-10 flex justify-center">
                    <x-avatar :user="$me" :presence="false" size="xl" class="ring-4 ring-white dark:ring-zinc-800" />
                </div>

                <div class="mt-2">
                    @if ($me->external_id)
                        <a href="{{ route('people.show', $me) }}" wire:navigate class="hover:underline">
                            <x-ui.heading size="lg">{{ $me->display_name }}</x-ui.heading>
                        </a>
                    @else
                        <x-ui.heading size="lg">{{ $me->display_name }}</x-ui.heading>
                    @endif
                    @if ($me->job_title)
                        <x-ui.text size="sm">{{ $me->job_title }}</x-ui.text>
                    @endif
                </div>

                {{-- Stats --}}
                <div class="mt-4 grid grid-cols-3 divide-x divide-sand-200 dark:divide-zinc-700">
                    <div class="px-1">
                        <x-ui.heading size="lg">{{ $followersCount }}</x-ui.heading>
                        <x-ui.text size="sm">{{ __('Followers') }}</x-ui.text>
                    </div>
                    <div class="px-1">
                        <x-ui.heading size="lg">{{ $followingCount }}</x-ui.heading>
                        <x-ui.text size="sm">{{ __('Following') }}</x-ui.text>
                    </div>
                    <div class="px-1">
                        <x-ui.heading size="lg">{{ $postsCount }}</x-ui.heading>
                        <x-ui.text size="sm">{{ __('Posts') }}</x-ui.text>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-3">
            <div class="flex items-center justify-between">
                <x-ui.heading size="sm">{{ __('My groups') }}</x-ui.heading>
                <a href="{{ route('groups') }}" wire:navigate class="text-sm text-brand-600 hover:underline">{{ __('Browse') }}</a>
            </div>
            @forelse ($myGroups as $group)
                <a wire:key="rail-group-{{ $group->id }}" href="{{ route('groups.show', $group) }}" wire:navigate
                    class="flex items-center gap-2.5 text-sm hover:underline">
                    {{-- The group's own icon, like the sidebar. Sized to 20px to sit inline with the text. --}}
                    <x-avatar :src="$group->avatarUrl()" :name="$group->name" icon="user-group" :presence="false" size="xs" class="[&>span]:!size-5" />
                    <span class="truncate">{{ $group->name }}</span>
                </a>
            @empty
                <x-ui.text size="sm">
                    {{ __('You are not in any groups yet.') }}
                </x-ui.text>
            @endforelse
        </x-ui.card>

        @php
            // Rotating tints keep each tile distinct without an admin having to pick a colour.
            $shortcutTints = [
                'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300',
                'bg-gold-50 text-gold-600 dark:bg-gold-500/15 dark:text-gold-300',
                'bg-sky-portal-100 text-sky-portal-500 dark:bg-sky-portal-500/15 dark:text-sky-portal-300',
                'bg-coral-50 text-coral-600 dark:bg-coral-500/15 dark:text-coral-300',
            ];
        @endphp
        <x-ui.card>
            <x-ui.heading size="sm" class="mb-3">{{ __('Quick links') }}</x-ui.heading>
            @if ($shortcuts->isEmpty())
                <x-ui.text size="sm">{{ __('No shortcuts yet.') }}</x-ui.text>
            @else
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach ($shortcuts as $i => $shortcut)
                        <a
                            wire:key="rail-shortcut-{{ $shortcut->id }}"
                            href="{{ $shortcut->url }}"
                            @if ($shortcut->opens_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                            class="flex flex-col gap-2 rounded-2xl border border-sand-200 p-3 transition hover:border-brand-300 hover:shadow-soft dark:border-white/10 dark:hover:border-brand-500"
                        >
                            <span class="grid size-9 place-items-center rounded-xl {{ $shortcutTints[$i % count($shortcutTints)] }}">
                                @if ($shortcut->hasCustomSvg())
                                    {{-- Sanitised on save; safe to render. --}}
                                    <span class="flex size-5 items-center justify-center [&>svg]:size-5 [&>svg]:w-auto">{!! $shortcut->svg !!}</span>
                                @else
                                    <x-ui.icon :name="$shortcut->iconName()" variant="mini" class="size-5" />
                                @endif
                            </span>
                            <span class="truncate text-sm font-medium text-ink-800 dark:text-zinc-200">{{ $shortcut->label }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </aside>

    {{-- Center column: composer, tabs and the feed --}}
    <div class="min-w-0 flex-1">
    <x-post-composer :me="$me" :placeholder="__('Share something with your colleagues…')"
        :attachments="$attachments" :mentionables="$mentionables" post-test="publish-button" />

    {{-- Tabs --}}
    <x-ui.tabs :selected="$tab" property="tab" class="mt-6">
        <x-ui.tab name="for-you">{{ __('For you') }}</x-ui.tab>
        <x-ui.tab name="latest">{{ __('Latest') }}</x-ui.tab>
    </x-ui.tabs>

    {{-- Feed --}}
    <div class="mt-4 space-y-4">
        @forelse ($posts as $post)
            <x-post-card :post="$post" :mentionables="$mentionables" :mention-map="$mentionMap" wire:key="post-{{ $post->id }}" />
        @empty
            <x-ui.alert icon="rss">
                {{ __('Your feed is quiet. Join a group or follow colleagues to see their posts here.') }}
            </x-ui.alert>
        @endforelse
    </div>
    </div>

    {{-- Right rail: widgets arrive with later phases (directory, events). Placeholders for now. --}}
    <aside class="hidden w-80 shrink-0 space-y-4 xl:block">
        {{-- Upcoming birthdays. Month and day only — never a year, so no age is revealed. --}}
        <x-ui.card class="space-y-3">
            <div class="flex items-center gap-2">
                <x-ui.icon name="cake" variant="mini" class="text-brand-500 dark:text-brand-400" />
                <x-ui.heading size="sm">{{ __('Upcoming birthdays') }}</x-ui.heading>
            </div>
            @forelse ($upcomingBirthdays as $person)
                @php($isToday = $person->birthdayIsToday())
                <a
                    wire:key="rail-bday-{{ $person->id }}"
                    href="{{ $person->external_id ? route('people.show', $person) : '#' }}"
                    @if ($person->external_id) wire:navigate @endif
                    @class([
                        'flex items-center gap-3 rounded-xl transition',
                        'bg-gradient-to-r from-brand-50 to-gold-50 p-2 dark:from-brand-500/10 dark:to-gold-500/10' => $isToday,
                        'hover:bg-sand-50 dark:hover:bg-white/5 -mx-1 px-1 py-1' => ! $isToday,
                    ])
                >
                    <div class="relative shrink-0">
                        <x-avatar :user="$person" :presence="false" size="sm" />
                        @if ($isToday)
                            <span class="absolute -bottom-1 -right-1 grid size-4 place-items-center rounded-full bg-white text-[10px] shadow-soft dark:bg-zinc-800">🎂</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <span class="block truncate text-sm font-medium text-ink-800 dark:text-zinc-200">{{ $person->display_name }}</span>
                        <span @class([
                            'block text-xs',
                            'font-semibold text-brand-600 dark:text-brand-400' => $isToday,
                            'text-mist-500' => ! $isToday,
                        ])>
                            {{ $isToday ? __('Birthday today! 🎉') : $person->nextBirthday()?->translatedFormat('j. F') }}
                        </span>
                    </div>
                </a>
            @empty
                <x-ui.text size="sm">{{ __('No upcoming birthdays.') }}</x-ui.text>
            @endforelse
        </x-ui.card>

        <x-ui.card class="space-y-3">
            <div class="flex items-center gap-2">
                <x-ui.icon name="calendar" variant="mini" class="text-brand-500 dark:text-brand-400" />
                <x-ui.heading size="sm">{{ __('Upcoming events') }}</x-ui.heading>
            </div>
            @forelse ($upcomingEvents as $event)
                <a wire:key="rail-event-{{ $event->id }}" href="{{ route('events.show', $event) }}" wire:navigate class="block hover:opacity-80">
                    <x-ui.text class="truncate font-medium text-ink-800 dark:text-zinc-200">{{ $event->title }}</x-ui.text>
                    <x-ui.text size="sm">{{ $event->starts_at->translatedFormat('j. M, H:i') }}</x-ui.text>
                </a>
            @empty
                <x-ui.text size="sm">{{ __('No upcoming events.') }}</x-ui.text>
            @endforelse
        </x-ui.card>

        <x-rail.placeholder :heading="__('New colleagues')" icon="user-plus" />
    </aside>

    <x-post-media-viewer />
    <x-post-reactions-modal :reactions="$postReactionList" />
</div>
