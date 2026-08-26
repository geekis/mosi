<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

test('a mention in a post renders as a profile link carrying the person\'s info', function () {
    $viewer = User::factory()->create();
    $egill = User::factory()->create([
        'display_name' => 'Egill Heinesen',
        'external_id' => 'egill-1',
        'job_title' => 'Landvörður',
    ]);
    Post::factory()->create(['user_id' => $viewer->id, 'body' => 'Sæll @EgillHeinesen!']);

    Livewire::actingAs($viewer)
        ->test('pages::feed')
        ->assertSeeHtml('href="'.route('people.show', $egill).'"')
        ->assertSee('@EgillHeinesen')
        ->assertSee('Landvörður'); // shown in the hover card
});

test('a mention of someone without a profile renders without a link', function () {
    $viewer = User::factory()->create();
    User::factory()->create(['display_name' => 'Nafnlaus Notandi', 'external_id' => null]);
    Post::factory()->create(['user_id' => $viewer->id, 'body' => 'Hi @NafnlausNotandi']);

    Livewire::actingAs($viewer)
        ->test('pages::feed')
        ->assertSee('@NafnlausNotandi')
        ->assertDontSeeHtml('/people/NafnlausNotandi');
});

test('an @handle with no matching person stays plain text', function () {
    $viewer = User::factory()->create();
    Post::factory()->create(['user_id' => $viewer->id, 'body' => 'Hi @EnginnHér']);

    Livewire::actingAs($viewer)
        ->test('pages::feed')
        ->assertSee('@EnginnHér');
});
