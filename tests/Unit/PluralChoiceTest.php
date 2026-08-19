<?php

declare(strict_types=1);

use App\Support\Localization\PluralChoice;

describe('Icelandic pluralisation', function () {
    it('uses the singular form for counts ending in 1 but not 11', function (int $count) {
        expect(PluralChoice::isSingular($count, 'is'))->toBeTrue();
    })->with([1, 21, 31, 41, 101, 121, 1001]);

    it('uses the plural form for every other count', function (int $count) {
        expect(PluralChoice::isSingular($count, 'is'))->toBeFalse();
    })->with([0, 2, 5, 11, 12, 20, 100, 111, 1011]);
});

describe('English pluralisation', function () {
    it('uses the singular form only for exactly one', function () {
        expect(PluralChoice::isSingular(1, 'en'))->toBeTrue();
    });

    it('uses the plural form for everything else', function (int $count) {
        expect(PluralChoice::isSingular($count, 'en'))->toBeFalse();
    })->with([0, 2, 11, 21, 101]);
});

it('substitutes the :count placeholder into the chosen form', function () {
    expect(PluralChoice::make(21, ':count færsla', ':count færslur', 'is'))->toBe('21 færsla')
        ->and(PluralChoice::make(11, ':count færsla', ':count færslur', 'is'))->toBe('11 færslur')
        ->and(PluralChoice::make(1, ':count post', ':count posts', 'en'))->toBe('1 post')
        ->and(PluralChoice::make(3, ':count post', ':count posts', 'en'))->toBe('3 posts');
});

it('treats negative counts by their absolute value', function () {
    expect(PluralChoice::isSingular(-21, 'is'))->toBeTrue()
        ->and(PluralChoice::isSingular(-11, 'is'))->toBeFalse();
});
