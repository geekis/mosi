<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function tabMember(Group $group, string $role = GroupRole::Member->value): User
{
    $user = User::factory()->create();
    $group->members()->attach($user->id, ['role' => $role]);

    return $user;
}

beforeEach(function () {
    Storage::fake('local');
});

// --- Tab visibility --------------------------------------------------------

test('the group page defaults to the feed tab', function () {
    $group = Group::factory()->create();
    $member = tabMember($group);

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->assertSet('tab', 'feed')
        ->assertSee(__('Feed'));
});

test('a member sees members-only tab content, not the join prompt', function () {
    $group = Group::factory()->create();
    $member = tabMember($group);

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->set('tab', 'files')
        ->assertSee(__('Upload file'))
        ->assertDontSee(__('Members only — join to view.'));
});

test('a non-member sees a members-only callout instead of the content', function () {
    $group = Group::factory()->create(); // open, viewable, but content is member-only
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::groups.show', ['group' => $group])
        ->set('tab', 'files')
        ->assertSee(__('Members only — join to view.'))
        ->assertDontSee(__('Upload file'));
});

test('a non-member cannot open a secret group page at all', function () {
    $secret = Group::factory()->secret()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('groups.show', $secret))
        ->assertNotFound();
});

test('the feed right rail shows member widgets and a members preview', function () {
    $group = Group::factory()->create();
    $member = tabMember($group);

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->assertSee(__('Upcoming events'))
        ->assertSee(__('More'))
        ->assertSee(__(':count members', ['count' => 1]));
});

test('a non-member feed hides member-only widgets but keeps the members preview', function () {
    $group = Group::factory()->create();
    tabMember($group); // give the group a member so the preview is not empty
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::groups.show', ['group' => $group])
        ->assertDontSee(__('Upcoming events'))
        ->assertSee(__('More'));
});

test('the More button switches to the members tab', function () {
    $group = Group::factory()->create();
    $member = tabMember($group);

    Livewire::actingAs($member)
        ->test('pages::groups.show', ['group' => $group])
        ->set('tab', 'members')
        ->assertSet('tab', 'members')
        ->assertSee($member->display_name);
});

test('the members tab shows job title, presence and a message button', function () {
    $group = Group::factory()->create();
    $viewer = tabMember($group);
    $colleague = User::factory()->create(['job_title' => 'Landvörður']);
    $group->members()->attach($colleague->id, ['role' => GroupRole::Member->value]);

    Livewire::actingAs($viewer)
        ->test('pages::groups.show', ['group' => $group])
        ->set('tab', 'members')
        ->assertSee('Landvörður')
        ->assertSee(__('Message'));
});

test('a member starts a direct chat with another member from the members tab', function () {
    $group = Group::factory()->create();
    $viewer = tabMember($group);
    $colleague = tabMember($group);

    Livewire::actingAs($viewer)
        ->test('pages::groups.show', ['group' => $group])
        ->set('tab', 'members')
        ->call('message', $colleague->id)
        ->assertRedirect();
});

test('the message action ignores a user who is not a member', function () {
    $group = Group::factory()->create();
    $viewer = tabMember($group);
    $outsider = User::factory()->create();

    Livewire::actingAs($viewer)
        ->test('pages::groups.show', ['group' => $group])
        ->call('message', $outsider->id)
        ->assertNoRedirect();
});

// --- Events tab wiring -----------------------------------------------------

test('a member creates a group event from the events tab', function () {
    $group = Group::factory()->create();
    $member = tabMember($group);

    Livewire::actingAs($member)
        ->test('pages::groups.events-tab', ['group' => $group])
        ->set('title', 'Hópfundur')
        ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('create')
        ->assertHasNoErrors();

    expect(Event::query()->where('group_id', $group->id)->where('created_by', $member->id)->exists())->toBeTrue();
});

// --- Files tab wiring ------------------------------------------------------

test('a member uploads and deletes a file from the files tab', function () {
    $group = Group::factory()->create();
    $member = tabMember($group);

    $component = Livewire::actingAs($member)
        ->test('pages::groups.files-tab', ['group' => $group])
        ->set('file', UploadedFile::fake()->create('handbook.pdf', 50, 'application/pdf'))
        ->call('upload')
        ->assertHasNoErrors();

    $media = $group->fresh()->getMedia('files');
    expect($media)->toHaveCount(1);

    $component->call('delete', $media->first()->id);
    expect($group->fresh()->getMedia('files'))->toHaveCount(0);
});

// --- Media tab wiring ------------------------------------------------------

test('a member uploads an image from the media tab', function () {
    $group = Group::factory()->create();
    $member = tabMember($group);

    Livewire::actingAs($member)
        ->test('pages::groups.media-tab', ['group' => $group])
        ->set('upload', UploadedFile::fake()->image('photo.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect($group->fresh()->getMedia('gallery'))->toHaveCount(1);
});
