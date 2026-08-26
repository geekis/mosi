<?php

declare(strict_types=1);

use App\Actions\Groups\CreateGroup;
use App\Enums\GroupRole;
use App\Enums\GroupType;
use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;

test('any staff member can create a group and becomes its owner', function () {
    $creator = User::factory()->create();

    $group = app(CreateGroup::class)($creator, [
        'name' => ['is' => 'Sundhópurinn', 'en' => 'Swimming group'],
        'description' => ['is' => 'Sund á miðvikudögum.', 'en' => 'Swimming on Wednesdays.'],
        'type' => GroupType::Interest,
        'visibility' => GroupVisibility::Open,
    ]);

    expect($group->created_by)->toBe($creator->id)
        ->and($group->hasMember($creator))->toBeTrue()
        ->and($group->members()->whereKey($creator->id)->first()->pivot->role)->toBe(GroupRole::Owner->value)
        ->and($creator->can('update', $group))->toBeTrue();
});

test('a department group cannot be created by hand', function () {
    $creator = User::factory()->create();

    app(CreateGroup::class)($creator, [
        'name' => ['is' => 'Fölsuð deild', 'en' => 'Fake department'],
        'type' => GroupType::Department,
        'visibility' => GroupVisibility::Open,
    ]);
})->throws(DomainException::class);

test('the groups page creates a group and redirects to it', function () {
    $creator = User::factory()->create();

    Livewire::actingAs($creator)
        ->test('pages::groups')
        ->set('name_is', 'Skíðahópurinn')
        ->set('name_en', 'Ski group')
        ->set('type', 'interest')
        ->set('visibility', 'secret')
        ->call('createGroup')
        ->assertHasNoErrors()
        ->assertRedirect();

    $group = Group::query()->where('created_by', $creator->id)->firstOrFail();

    expect($group->visibility)->toBe(GroupVisibility::Secret)
        ->and($group->hasMember($creator))->toBeTrue();
});

test('creating a group requires both names', function () {
    $creator = User::factory()->create();

    Livewire::actingAs($creator)
        ->test('pages::groups')
        ->set('name_is', 'Bara íslenska')
        ->call('createGroup')
        ->assertHasErrors(['name_en']);

    expect(Group::query()->where('created_by', $creator->id)->exists())->toBeFalse();
});
