<?php

declare(strict_types=1);

namespace App\Actions\Microsoft;

use App\Models\User;
use App\Support\Microsoft\GraphClient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a user's Outlook calendar for a date window via `GET /users/{oid}/calendarView`,
 * which expands recurring series into their occurrences (plain `/events` does not). Results
 * are briefly cached so paging back and forth on a profile does not hammer Graph. Returns an
 * empty list when Graph is disabled or the user is not an Entra account, so the calendar
 * panel simply shows its empty state for the ~25% of staff without a mailbox.
 */
final class FetchUserCalendar
{
    private const int CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly GraphClient $graph) {}

    /**
     * @return list<array{subject: ?string, start: CarbonImmutable, end: CarbonImmutable, is_all_day: bool, show_as: string, sensitivity: string, location: ?string, organizer: ?string, attendees: list<string>}>
     */
    public function __invoke(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($user->entra_oid === null || ! $this->graph->enabled()) {
            return [];
        }

        $cacheKey = sprintf('calendar:%d:%s:%s', $user->id, $from->toDateString(), $to->toDateString());

        $cached = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user, $from, $to): array {
            // A Graph failure (e.g. the app is not yet authorised for this mailbox — a 403
            // ErrorAccessDenied) must never take down the profile page: log it and fall back
            // to an empty agenda. The empty result is cached briefly so a persistent
            // misconfiguration does not re-hit Graph on every render.
            try {
                $response = $this->graph->get("users/{$user->entra_oid}/calendarView", [
                    'startDateTime' => $from->toIso8601String(),
                    'endDateTime' => $to->toIso8601String(),
                    '$select' => 'subject,start,end,location,isAllDay,showAs,sensitivity,organizer,attendees',
                    '$orderby' => 'start/dateTime',
                    '$top' => 100,
                ]);
            } catch (Throwable $e) {
                Log::warning('Microsoft Graph calendar read failed.', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);

                return [];
            }

            $events = is_array($response) ? ($response['value'] ?? []) : [];

            return array_values(array_map($this->transform(...), $events));
        });

        // Rehydrate the cached ISO strings into Carbon instances here, outside the cache.
        // Caching Carbon objects directly can fail to unserialize ("incomplete object").
        return array_map(function (array $event): array {
            $event['start'] = CarbonImmutable::parse($event['start']);
            $event['end'] = CarbonImmutable::parse($event['end']);

            return $event;
        }, $cached);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{subject: ?string, start: string, end: string, is_all_day: bool, show_as: string, sensitivity: string, location: ?string, organizer: ?string, attendees: list<string>}
     */
    private function transform(array $event): array
    {
        $subject = $event['subject'] ?? null;

        return [
            'subject' => is_string($subject) && $subject !== '' ? $subject : null,
            'start' => $this->parseDateTime($event['start'] ?? null)->toIso8601String(),
            'end' => $this->parseDateTime($event['end'] ?? null)->toIso8601String(),
            'is_all_day' => (bool) ($event['isAllDay'] ?? false),
            'show_as' => is_string($event['showAs'] ?? null) ? $event['showAs'] : 'busy',
            'sensitivity' => is_string($event['sensitivity'] ?? null) ? $event['sensitivity'] : 'normal',
            'location' => $this->displayName($event['location'] ?? null),
            'organizer' => $this->personName($event['organizer'] ?? null),
            'attendees' => $this->attendeeNames($event['attendees'] ?? null),
        ];
    }

    private function parseDateTime(mixed $slot): CarbonImmutable
    {
        $dateTime = is_array($slot) ? ($slot['dateTime'] ?? null) : null;
        $timeZone = is_array($slot) && is_string($slot['timeZone'] ?? null) ? $slot['timeZone'] : 'UTC';

        if (! is_string($dateTime)) {
            return CarbonImmutable::now(config('app.timezone'));
        }

        return CarbonImmutable::parse($dateTime, $timeZone)->setTimezone(config('app.timezone'));
    }

    private function displayName(mixed $location): ?string
    {
        $name = is_array($location) ? ($location['displayName'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function personName(mixed $person): ?string
    {
        $name = is_array($person) ? ($person['emailAddress']['name'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return list<string>
     */
    private function attendeeNames(mixed $attendees): array
    {
        if (! is_array($attendees)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $attendee): ?string => is_array($attendee) ? $this->personName($attendee) : null,
            $attendees,
        )));
    }
}
