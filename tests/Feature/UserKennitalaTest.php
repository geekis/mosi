<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('stores the kennitala encrypted at rest', function () {
    $user = User::factory()->create();

    $raw = DB::table('users')->where('id', $user->id)->value('kennitala');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain($user->kennitala)
        ->and(Crypt::decryptString($raw))->toBe($user->kennitala);
});

it('keeps a SHA-256 hash of the kennitala in lockstep', function () {
    $user = User::factory()->create();

    expect($user->kennitala_hash)->toBe(hash('sha256', $user->kennitala));
});

it('normalises the kennitala so hyphenated and bare forms match', function () {
    $user = User::factory()->create();
    $bare = $user->kennitala;
    $hyphenated = substr($bare, 0, 6).'-'.substr($bare, 6);

    $user->kennitala = $hyphenated;
    $user->save();

    expect($user->fresh()->kennitala)->toBe($bare)
        ->and(User::findByKennitala($hyphenated)?->id)->toBe($user->id)
        ->and(User::findByKennitala($bare)?->id)->toBe($user->id);
});

it('never exposes the kennitala in array or JSON serialisation', function () {
    $user = User::factory()->create();
    $kennitala = $user->kennitala;
    $hash = $user->kennitala_hash;

    $array = $user->toArray();
    expect($array)->not->toHaveKey('kennitala')
        ->and($array)->not->toHaveKey('kennitala_hash');

    // The value must not leak even without its key (e.g. through a nested payload or a log).
    $json = $user->toJson();
    expect($json)->not->toContain($kennitala)
        ->and($json)->not->toContain($hash);

    // json_encode() routes through jsonSerialize()/toArray(), the shape most logs use.
    expect(json_encode($user))->not->toContain($kennitala);
});

it('locates a user by kennitala for the sync second-pass and reactivation', function () {
    $user = User::factory()->create();

    expect(User::findByKennitala($user->kennitala)?->is($user))->toBeTrue()
        ->and(User::findByKennitala('0000000000'))->toBeNull();
});
