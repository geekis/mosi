<?php

declare(strict_types=1);

namespace App\Support\Localization;

use Illuminate\Support\Facades\App;

/**
 * Locale-aware pluralisation.
 *
 * Laravel's `trans_choice` uses English-style pluralisation (singular only for
 * exactly 1). Icelandic is different: the singular form is used whenever the count
 * ends in 1 but not 11 — so 1, 21, 31, 101, 121 take the singular ("21 færsla"),
 * while 11, 111 and everything else take the plural ("11 færslur"). Use this helper
 * instead of `trans_choice` wherever a count is shown to staff.
 */
final class PluralChoice
{
    /**
     * Pick the correct form for a count and substitute it for the `:count`
     * placeholder in the chosen string.
     */
    public static function make(int $count, string $singular, string $plural, ?string $locale = null): string
    {
        $form = self::isSingular($count, $locale) ? $singular : $plural;

        return str_replace(':count', (string) $count, $form);
    }

    /**
     * Whether the given count takes the singular form in the given locale.
     *
     * Defaults to the active application locale.
     */
    public static function isSingular(int $count, ?string $locale = null): bool
    {
        $locale ??= App::getLocale();
        $absolute = abs($count);

        if ($locale === 'is') {
            return $absolute % 10 === 1 && $absolute % 100 !== 11;
        }

        // English (and the fallback for any other locale): singular only for exactly 1.
        return $absolute === 1;
    }
}
