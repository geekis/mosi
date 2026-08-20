<?php

declare(strict_types=1);

use App\Actions\Staff\EnrolIntoGroups;
use App\Actions\Staff\SyncStaffFromApi;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function pivotRole(Group $group, User $user): ?string
{
    return $group->members()->whereKey($user->id)->first()?->getAttribute('pivot')?->role;
}

it('enrols a member into their department group and the org-wide group', function () {
    $user = User::factory()->create([
        'organization_id' => '39164',
        'organization_name' => 'NVS Norðurland',
        'parent_organization_id' => '39162',
        'is_manager' => false,
    ]);

    app(EnrolIntoGroups::class)($user);

    $department = Group::query()->where('external_organization_id', '39164')->sole();
    $orgWide = Group::query()->where('external_organization_id', '39162')->sole();

    expect($department->hasMember($user))->toBeTrue()
        ->and($orgWide->hasMember($user))->toBeTrue()
        ->and(pivotRole($department, $user))->toBe(GroupRole::Member->value);

    app()->setLocale('is');
    expect($department->name)->toBe('NVS Norðurland')
        ->and($orgWide->name)->toBe('Allir starfsmenn');
});

it('makes a manager the owner of their department group and grants the group-owner role', function () {
    $user = User::factory()->manager()->create([
        'organization_id' => '39164',
        'organization_name' => 'NVS Norðurland',
        'parent_organization_id' => '39162',
    ]);

    app(EnrolIntoGroups::class)($user);

    $department = Group::query()->where('external_organization_id', '39164')->sole();
    $orgWide = Group::query()->where('external_organization_id', '39162')->sole();

    expect(pivotRole($department, $user))->toBe(GroupRole::Owner->value)
        ->and(pivotRole($orgWide, $user))->toBe(GroupRole::Member->value)
        ->and($user->hasAnyRoleName('group-owner'))->toBeTrue();
});

it('is idempotent — a second enrolment creates no duplicates', function () {
    $user = User::factory()->create([
        'organization_id' => '39164',
        'organization_name' => 'NVS Norðurland',
        'parent_organization_id' => '39162',
    ]);

    app(EnrolIntoGroups::class)($user);
    app(EnrolIntoGroups::class)($user);

    $department = Group::query()->where('external_organization_id', '39164')->sole();

    expect(Group::query()->where('external_organization_id', '39164')->count())->toBe(1)
        ->and(Group::query()->where('external_organization_id', '39162')->count())->toBe(1)
        ->and($department->members()->count())->toBe(1);
});

it('the staff sync enrols every synced member into their groups', function () {
    config([
        'services.staff_api.url' => 'https://staff.test/api/employees',
        'services.staff_api.username' => 'svc',
        'services.staff_api.password' => 'secret',
    ]);
    $rows = json_decode((string) file_get_contents(base_path('tests/Fixtures/staff/payload.json')), true)['rows'];
    Http::fake(['staff.test/*' => Http::response(['items' => $rows, 'hasMore' => false])]);

    app(SyncStaffFromApi::class)();

    $norduland = Group::query()->where('external_organization_id', '39164')->sole();
    $orgWide = Group::query()->where('external_organization_id', '39162')->sole();
    $manager = User::query()->where('external_id', '100002')->sole(); // managerFlag Y
    $ranger = User::query()->where('external_id', '100001')->sole();

    expect(pivotRole($norduland, $manager))->toBe(GroupRole::Owner->value)
        ->and($norduland->hasMember($ranger))->toBeTrue()
        ->and($orgWide->members()->count())->toBe(4); // the whole payload joins the org-wide group
});
