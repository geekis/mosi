<?php

declare(strict_types=1);

use App\Actions\Gdpr\ExportUserData;
use App\Models\Post;
use App\Models\User;

test('security headers are present on web responses', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('the teams tab context relaxes framing to the teams host set', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard', ['context' => 'teams']));

    $response->assertHeaderMissing('X-Frame-Options')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://teams.microsoft.com https://*.teams.microsoft.com https://*.skype.com https://*.office.com https://*.microsoft.com;")
        ->assertPlainCookie('mosi_teams_embed', '1');
});

test('the teams embed cookie keeps framing relaxed without the query param', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withUnencryptedCookie('mosi_teams_embed', '1')
        ->get(route('dashboard'));

    $response->assertHeaderMissing('X-Frame-Options')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://teams.microsoft.com https://*.teams.microsoft.com https://*.skype.com https://*.office.com https://*.microsoft.com;");
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
