<?php

declare(strict_types=1);

use App\Actions\Staff\SendStaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('sends an invitation and stores a single-use token', function () {
    Notification::fake();
    $user = User::factory()->entra()->create(); // active, has email, no password yet

    $sent = app(SendStaffInvitation::class)($user);

    expect($sent)->toBeTrue()
        ->and(DB::table('invitation_tokens')->where('email', $user->email)->exists())->toBeTrue();

    Notification::assertSentTo($user, StaffInvitation::class);
});

it('never invites a deactivated or email-less account', function () {
    Notification::fake();
    $inactive = User::factory()->inactive()->create();
    $noEmail = User::factory()->withoutEmail()->create();

    expect(app(SendStaffInvitation::class)($inactive))->toBeFalse()
        ->and(app(SendStaffInvitation::class)($noEmail))->toBeFalse();

    Notification::assertNothingSent();
});

it('resends a fresh invitation', function () {
    Notification::fake();
    $user = User::factory()->entra()->create();

    app(SendStaffInvitation::class)($user);
    app(SendStaffInvitation::class)($user);

    Notification::assertSentToTimes($user, StaffInvitation::class, 2);
});

it('invites only users still without a password via the command', function () {
    Notification::fake();
    User::factory()->count(3)->entra()->create();        // no password → pending
    User::factory()->count(2)->passwordAuth()->create(); // already have a password

    $this->artisan('app:invite-staff')->assertSuccessful();

    Notification::assertCount(3);
});
