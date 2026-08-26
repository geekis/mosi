<?php

declare(strict_types=1);

use App\Models\Shortcut;
use App\Models\User;
use Livewire\Livewire;

test('the mega menu shows active shortcuts to any user', function () {
    Shortcut::factory()->create([
        'label' => 'Vinnustund',
        'url' => 'https://vinnustund.example',
        'category' => 'HR',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shortcut-menu')
        ->assertSee('Vinnustund')
        ->assertSee('HR')
        ->assertSee('https://vinnustund.example');
});

test('inactive shortcuts are hidden', function () {
    Shortcut::factory()->create(['label' => 'VisibleOne']);
    Shortcut::factory()->inactive()->create(['label' => 'HiddenOne']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shortcut-menu')
        ->assertSee('VisibleOne')
        ->assertDontSee('HiddenOne');
});

test('shortcuts render in sort order', function () {
    Shortcut::factory()->create(['label' => 'Second', 'category' => null, 'sort_order' => 2]);
    Shortcut::factory()->create(['label' => 'First', 'category' => null, 'sort_order' => 1]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shortcut-menu')
        ->assertSeeInOrder(['First', 'Second']);
});

test('a new-tab shortcut renders a blank target', function () {
    Shortcut::factory()->create([
        'label' => 'External',
        'opens_in_new_tab' => true,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shortcut-menu')
        ->assertSee('target="_blank"', escape: false);
});

test('a shortcut renders its chosen icon and falls back safely on a bad value', function () {
    expect((new Shortcut(['icon' => 'calendar-days', 'opens_in_new_tab' => true]))->iconName())
        ->toBe('calendar-days')
        ->and((new Shortcut(['icon' => 'not-a-real-icon', 'opens_in_new_tab' => true]))->iconName())
        ->toBe('arrow-top-right-on-square')
        ->and((new Shortcut(['icon' => null, 'opens_in_new_tab' => false]))->iconName())
        ->toBe('link');
});

test('custom svg is sanitised on save, stripping scripts and handlers', function () {
    $shortcut = Shortcut::factory()->create([
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><script>alert(1)</script><path d="M1 2 L3 4" onclick="evil()"/></svg>',
    ]);

    $svg = $shortcut->fresh()->svg;

    expect($svg)->not->toBeNull()
        ->and($svg)->not->toContain('<script')
        ->and($svg)->not->toContain('alert(1)')
        ->and($svg)->not->toContain('onclick')
        ->and($svg)->toContain('<path');
});

test('a blank svg is normalised to null', function () {
    $shortcut = Shortcut::factory()->create(['svg' => '   ']);

    expect($shortcut->fresh()->svg)->toBeNull()
        ->and($shortcut->fresh()->hasCustomSvg())->toBeFalse();
});

test('a shortcut with custom svg renders it in place of a heroicon', function () {
    Shortcut::factory()->create([
        'label' => 'CustomIconLink',
        'icon' => 'calendar-days',
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 2 L3 4"/></svg>',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shortcut-menu')
        ->assertSee('M1 2', escape: false);
});

test('the empty state is shown when there are no shortcuts', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::shortcut-menu')
        ->assertSee(__('No shortcuts yet.'));
});
