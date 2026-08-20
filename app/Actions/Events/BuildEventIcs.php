<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;

/**
 * Render a single event as an RFC 5545 iCalendar document for download / calendar
 * subscription. Times are emitted in UTC; an all-day event uses a DATE value.
 */
final class BuildEventIcs
{
    public function __invoke(Event $event): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mosi//NVS//IS',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@mosi',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            $this->start($event),
            ...$this->end($event),
            'SUMMARY:'.$this->escape((string) $event->getTranslation('title', 'is')),
        ];

        if ($event->description !== null && $event->description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escape((string) $event->getTranslation('description', 'is'));
        }

        if ($event->location !== null && $event->location !== '') {
            $lines[] = 'LOCATION:'.$this->escape($event->location);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function start(Event $event): string
    {
        return $event->is_all_day
            ? 'DTSTART;VALUE=DATE:'.$event->starts_at->format('Ymd')
            : 'DTSTART:'.$event->starts_at->utc()->format('Ymd\THis\Z');
    }

    /**
     * @return list<string>
     */
    private function end(Event $event): array
    {
        if ($event->ends_at === null) {
            return [];
        }

        return [$event->is_all_day
            ? 'DTEND;VALUE=DATE:'.$event->ends_at->format('Ymd')
            : 'DTEND:'.$event->ends_at->utc()->format('Ymd\THis\Z'),
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
