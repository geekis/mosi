<?php

declare(strict_types=1);

use App\Support\HtmlContent;

it('keeps allowed formatting elements', function () {
    $clean = HtmlContent::sanitize('<p>Hello <strong>world</strong></p><ul><li>one</li></ul>');

    expect($clean)
        ->toContain('<p>Hello <strong>world</strong></p>')
        ->toContain('<li>one</li>');
});

it('strips script tags and event handlers', function () {
    $clean = HtmlContent::sanitize('<p>Hi</p><script>alert(1)</script><img src=x onerror="alert(1)">');

    expect($clean)
        ->toContain('<p>Hi</p>')
        ->not->toContain('script')
        ->not->toContain('onerror')
        ->not->toContain('<img');
});

it('drops dangerous link schemes but keeps https links', function () {
    $clean = HtmlContent::sanitize('<a href="javascript:alert(1)">x</a><a href="https://nattura.is">y</a>');

    expect($clean)
        ->not->toContain('javascript:')
        ->toContain('href="https://nattura.is"')
        ->toContain('rel="noopener nofollow ugc"');
});

it('preserves mention markers', function () {
    $clean = HtmlContent::sanitize('<span data-mention="5">@Egill</span>');

    expect($clean)->toContain('data-mention="5"');
});

it('returns an empty string for null input', function () {
    expect(HtmlContent::sanitize(null))->toBe('');
});
