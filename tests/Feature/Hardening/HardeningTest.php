<?php

declare(strict_types=1);

use App\Actions\Gdpr\ExportUserData;
use App\Models\Post;
use App\Models\User;

test('security headers are present on web responses', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    // Framing is governed by CSP frame-ancestors (self + Teams), not X-Frame-Options,
    // so the Teams web client can embed the app even when its context cookie is blocked
    // as third-party.
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://teams.microsoft.com https://*.teams.microsoft.com https://*.cloud.microsoft https://*.skype.com https://*.office.com https://*.microsoft.com;");
});

test('the teams tab context is remembered with a cookie', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard', ['context' => 'teams']))
        ->assertPlainCookie('mosi_teams_embed', '1');
});

test('the account menu is always rendered and marked for client-side hiding in Teams', function () {
    $user = User::factory()->create();

    // The menu is always in the DOM; it is hidden purely client-side when the page is
    // actually framed by Teams, so a browser session that once visited Teams still shows
    // it (the previous cookie-based approach leaked the hidden state into the browser).
    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('logout-button')
        ->assertSee('data-hide-in-teams', false)
        ->assertSee('teams-embed', false);
});

test('the data export includes the user own content', function () {
    $user = User::factory()->create(['display_name' => 'Gagna Gunnar']);
    Post::factory()->create(['user_id' => $user->id, 'body' => 'MY-EXPORTED-POST']);

    $export = app(ExportUserData::class)($user);

    expect($export['profile']['display_name'])->toBe('Gagna Gunnar')
        ->and($export['posts'])->toHaveCount(1)
        ->and($export['posts'][0]['body'])->toBe('MY-EXPORTED-POST');
});

test('the data export never contains the kennitala', function () {
    $kennitala = '0101994569';
    $user = User::factory()->create();
    $user->kennitala = $kennitala;
    $user->save();

    $json = json_encode(app(ExportUserData::class)($user->fresh()));

    expect($json)->not->toContain($kennitala)
        ->and(array_key_exists('kennitala', app(ExportUserData::class)($user)['profile']))->toBeFalse();
});

test('the export endpoint returns the acting user data only', function () {
    $user = User::factory()->create(['display_name' => 'Endapunktur Erla']);

    $this->actingAs($user)
        ->get(route('me.export'))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="mosi-data-export.json"')
        ->assertSee('Endapunktur Erla');
});

test('the export endpoint requires authentication', function () {
    $this->get(route('me.export'))->assertRedirect();
});
