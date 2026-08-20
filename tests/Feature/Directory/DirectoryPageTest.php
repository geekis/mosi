<?php

declare(strict_types=1);

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('the directory page renders staff', function () {
    $viewer = User::factory()->create();
    User::factory()->create(['display_name' => 'Sýnileg Sara']);

    Livewire::actingAs($viewer)
        ->test('pages::directory')
        ->assertOk()
        ->assertSee('Sýnileg Sara');
});

test('searching narrows the rendered list', function () {
    $viewer = User::factory()->create();
    User::factory()->create(['display_name' => 'Finnandi Finnur', 'job_title' => 'Sérfræðingur']);
    User::factory()->create(['display_name' => 'Ósýnileg Óöld', 'job_title' => 'Bókari']);

    Livewire::actingAs($viewer)
        ->test('pages::directory')
        ->set('search', 'Sérfræðingur')
        ->assertSee('Finnandi Finnur')
        ->assertDontSee('Ósýnileg Óöld');
});

test('a secret group is never exposed in the directory', function () {
    $viewer = User::factory()->create();
    $member = User::factory()->create(['display_name' => 'Leyni Meðlimur']);
    $secret = Group::factory()->secret()->create(['name' => ['en' => 'Covert Ops', 'is' => 'Leyniverkefni']]);
    $secret->members()->attach($member->id, ['role' => GroupRole::Member->value]);

    Livewire::actingAs($viewer)
        ->test('pages::directory')
        ->assertSee('Leyni Meðlimur')      // the person is listed
        ->assertDontSee('Leyniverkefni')   // their secret group is not
        ->assertDontSee('Covert Ops');
});

test('rendering the directory is a bounded number of queries', function () {
    $viewer = User::factory()->create();
    User::factory()->count(20)->create();

    DB::enableQueryLog();
    Livewire::actingAs($viewer)->test('pages::directory');
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // People query + pagination count + departments + media eager-load — never N+1 per row.
    expect($queries)->toBeLessThanOrEqual(12);
});
