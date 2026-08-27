<?php

declare(strict_types=1);

namespace App\Actions\Microsoft;

use App\Enums\PresenceStatus;
use App\Support\Microsoft\GraphClient;

/**
 * Reads Teams presence for a set of Entra users in one shot via
 * `POST /communications/getPresencesByUserId`. Graph accepts up to 650 ids per call, so
 * larger sets are chunked. Returns a map of `entra_oid => PresenceStatus`; ids Graph does
 * not answer for are simply absent (the caller treats a missing id as unchanged/offline).
 */
final class FetchPresences
{
    private const int MAX_IDS_PER_REQUEST = 650;

    public function __construct(private readonly GraphClient $graph) {}

    /**
     * @param  list<string>  $entraOids
     * @return array<string, PresenceStatus>
     */
    public function __invoke(array $entraOids): array
    {
        $statuses = [];

        foreach (array_chunk(array_values(array_unique($entraOids)), self::MAX_IDS_PER_REQUEST) as $chunk) {
            $response = $this->graph->post('communications/getPresencesByUserId', ['ids' => $chunk]);

            if (! is_array($response)) {
                continue;
            }

            foreach (($response['value'] ?? []) as $presence) {
                $oid = $presence['id'] ?? null;

                if (is_string($oid) && $oid !== '') {
                    $statuses[$oid] = PresenceStatus::fromGraphAvailability($presence['availability'] ?? null);
                }
            }
        }

        return $statuses;
    }
}
