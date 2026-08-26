<?php

namespace App\Models;

use Database\Factories\ShortcutFactory;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A staff shortcut surfaced in the header mega menu — a link to an external system or
 * an internal page. Managed by content managers and admins in the Filament panel;
 * visible to every authenticated user.
 *
 * @property int $id
 * @property string $label
 * @property string $url
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $svg
 * @property string|null $category
 * @property bool $opens_in_new_tab
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Shortcut extends Model
{
    /** @use HasFactory<ShortcutFactory> */
    use HasFactory;

    /**
     * The Heroicons an admin may pick for a shortcut. Restricting the choice to a known
     * set keeps the mega menu safe: an arbitrary icon name would throw at render and
     * break the header for every user.
     *
     * @var list<string>
     */
    public const ICONS = [
        'link',
        'globe-alt',
        'home',
        'building-office',
        'briefcase',
        'users',
        'user-group',
        'calendar-days',
        'clock',
        'envelope',
        'phone',
        'chat-bubble-left-right',
        'megaphone',
        'bell',
        'inbox',
        'newspaper',
        'book-open',
        'academic-cap',
        'document-text',
        'folder',
        'clipboard-document-list',
        'map',
        'chart-bar',
        'banknotes',
        'credit-card',
        'ticket',
        'computer-desktop',
        'wrench-screwdriver',
        'cog-6-tooth',
        'cloud',
        'key',
        'lock-closed',
        'shield-check',
        'question-mark-circle',
        'information-circle',
    ];

    /** @var list<string> */
    protected $fillable = [
        'label',
        'url',
        'description',
        'icon',
        'svg',
        'category',
        'opens_in_new_tab',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_in_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Custom SVG markup is sanitised the moment it is set, so the value stored in the
     * database — and later echoed into the header for every user — can never carry a
     * script, event handler, or external reference. An empty or unusable value is
     * normalised to null.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function svg(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                $value = trim((string) $value);

                if ($value === '') {
                    return null;
                }

                $clean = trim((new Sanitizer)->sanitize($value) ?: '');

                return $clean === '' ? null : $clean;
            },
        );
    }

    /**
     * Whether this shortcut renders custom SVG rather than a Heroicon.
     */
    public function hasCustomSvg(): bool
    {
        return $this->svg !== null && $this->svg !== '';
    }

    /**
     * The icon to render, guarding against a missing or unknown value so a bad row can
     * never break the header. Falls back to a link glyph that reflects the link target.
     */
    public function iconName(): string
    {
        if ($this->icon !== null && in_array($this->icon, self::ICONS, true)) {
            return $this->icon;
        }

        return $this->opens_in_new_tab ? 'arrow-top-right-on-square' : 'link';
    }

    /**
     * Only shortcuts that are switched on, in display order.
     *
     * @param  Builder<Shortcut>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('label');
    }
}
