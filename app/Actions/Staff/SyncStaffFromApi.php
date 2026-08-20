<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\AuthMethod;
use App\Models\StaffSyncRun;
use App\Models\User;
use App\Notifications\StaffSyncCircuitBreakerTripped;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sync users from the NVS staff API — the sole source of accounts.
 *
 * The confirmed activation model (see .ai/rules/staff-api.md): everyone in the payload
 * gets an active account; absence deactivates (never deletes); a returning person is
 * matched by external_id first, then kennitala, so their existing record — and its
 * posts, comments and memberships — is reactivated rather than duplicated.
 *
 * SOURCE: Oracle APEX ORDS endpoint (/ords/oebs/…) behind HTTP basic auth, paginated by
 * row_offset/fetch_size. The transport (basic auth, both the ORDS `items`/`hasMore` and a
 * `rows`/`totalRows` envelope) is handled in fetchAll(). The per-record shape — a person
 * with a nested `assignments` array — is still ASSUMED from the field mapping and lives in
 * normalise(). Verify it against a real response and adjust only normalise() if the ORDS
 * handler returns flat assignment rows or different field casing; the rest is shape-agnostic.
 */
final class SyncStaffFromApi
{
    /** A payload more than this fraction below the last successful run aborts the sync. */
    private const float CIRCUIT_BREAKER_THRESHOLD = 0.15;

    public function __invoke(): StaffSyncRun
    {
        $run = StaffSyncRun::create([
            'status' => StaffSyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            [$rawRows, $totalRows] = $this->fetchAll();

            if ($this->circuitBreakerTrips($totalRows, $last = $this->lastSuccessfulTotal())) {
                return $this->abort($run, (int) $last, $totalRows);
            }

            $records = array_map($this->normalise(...), $rawRows);

            $counts = ['created' => 0, 'updated' => 0, 'reactivated' => 0];
            $processed = [];
            $seenExternalIds = [];

            foreach ($records as $record) {
                $seenExternalIds[] = $record['external_id'];
                $processed[$record['external_id']] = $this->upsert($record, $counts);
            }

            $deactivated = $this->deactivateAbsent($seenExternalIds);
            $this->resolveSupervisors($processed);

            $run->update([
                'status' => StaffSyncRun::STATUS_SUCCESS,
                'total_rows' => $totalRows,
                'created_count' => $counts['created'],
                'updated_count' => $counts['updated'],
                'reactivated_count' => $counts['reactivated'],
                'deactivated_count' => $deactivated,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => StaffSyncRun::STATUS_FAILED,
                'errors' => ['message' => $e->getMessage()],
                'finished_at' => now(),
            ]);
            report($e);
        }

        return $run->refresh();
    }

    /**
     * Fetch every page from the Oracle ORDS endpoint. Paginates by row_offset until the
     * envelope's `hasMore` is false (or, if absent, until a short page is returned).
     * Handles both the ORDS `items` envelope and a `rows`/`totalRows` one.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function fetchAll(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('services.staff_api');
        [$baseUrl, $query] = $this->parseUrl((string) ($config['url'] ?? ''));

        $fetchSize = (int) ($query['fetch_size'] ?? $config['fetch_size'] ?? 1000);
        unset($query['row_offset'], $query['fetch_size']);

        $rows = [];
        $total = null;
        $offset = 0;

        do {
            $request = Http::acceptJson();

            if (filled($config['username'] ?? null)) {
                $request = $request->withBasicAuth((string) $config['username'], (string) ($config['password'] ?? ''));
            }

            $response = $request
                ->get($baseUrl, array_merge($query, ['row_offset' => $offset, 'fetch_size' => $fetchSize]))
                ->throw()
                ->json();

            $body = is_array($response) ? $response : [];
            $page = $this->extractRows($body);
            $rows = array_merge($rows, $page);

            if (isset($body['totalRows'])) {
                $total = (int) $body['totalRows'];
            }

            $hasMore = array_key_exists('hasMore', $body)
                ? (bool) $body['hasMore']
                : count($page) === $fetchSize;

            $offset += $fetchSize;
        } while ($hasMore && $page !== []);

        return [$rows, $total ?? count($rows)];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(array $body): array
    {
        $rows = $body['items'] ?? $body['rows'] ?? [];

        return is_array($rows)
            ? array_values(array_filter($rows, is_array(...)))
            : [];
    }

    /**
     * Split a configured URL into its base and query params, so pagination params can
     * be overridden per page without duplicating any already present in the URL.
     *
     * @return array{0: string, 1: array<array-key, mixed>}
     */
    private function parseUrl(string $url): array
    {
        $parts = parse_url($url);

        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        return [$base, $query];
    }

    private function lastSuccessfulTotal(): ?int
    {
        return StaffSyncRun::query()->lastSuccessful()->first()?->total_rows;
    }

    private function circuitBreakerTrips(int $totalRows, ?int $lastTotal): bool
    {
        return $lastTotal !== null
            && $lastTotal > 0
            && $totalRows < $lastTotal * (1 - self::CIRCUIT_BREAKER_THRESHOLD);
    }

    private function abort(StaffSyncRun $run, int $previousTotal, int $currentTotal): StaffSyncRun
    {
        $run->update([
            'status' => StaffSyncRun::STATUS_ABORTED,
            'total_rows' => $currentTotal,
            'errors' => ['circuit_breaker' => compact('previousTotal', 'currentTotal')],
            'finished_at' => now(),
        ]);

        Log::warning('Staff sync aborted by circuit breaker.', compact('previousTotal', 'currentTotal'));

        Notification::send(
            User::role('super-admin')->get(),
            new StaffSyncCircuitBreakerTripped($previousTotal, $currentTotal),
        );

        return $run->refresh();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, int>  $counts
     */
    private function upsert(array $record, array &$counts): User
    {
        $user = User::query()->where('external_id', $record['external_id'])->first()
            ?? User::findByKennitala($record['kennitala']);

        $isNew = $user === null;

        if ($isNew) {
            $user = new User;
            $user->kennitala = $record['kennitala'];
            // Seed the locale from the nationality hint; the user's own choice wins later.
            $user->locale = ($record['nationality'] ?? null) === 'IS' ? 'is' : 'en';
        }

        $wasInactive = ! $isNew && ! $user->is_active;

        $user->external_id = $record['external_id'];

        if ($user->kennitala_hash !== User::hashKennitala($record['kennitala'])) {
            $user->kennitala = $record['kennitala'];
        }

        $user->fill($this->syncedAttributes($record));

        // Preserve a linked (both) account; otherwise derive from the current email.
        if ($user->auth_method !== AuthMethod::Both) {
            $user->auth_method = AuthMethod::fromEmail($record['email']);
        }

        $user->is_active = true;

        if ($isNew) {
            $user->save();
            $counts['created']++;
        } elseif ($user->isDirty()) {
            $user->save();
            $wasInactive ? $counts['reactivated']++ : $counts['updated']++;
        }

        return $user;
    }

    /**
     * The attributes the sync owns and mass-assigns. Deliberately excludes kennitala,
     * locale, auth_method and is_active, which are handled explicitly, and supervisor_id,
     * which is resolved in the second pass.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function syncedAttributes(array $record): array
    {
        return Arr::only($record, [
            'display_name', 'email', 'phone', 'employee_number', 'person_type', 'job_title',
            'job_id', 'organization_id', 'organization_name', 'parent_organization_id',
            'assignment_status', 'assignment_starts_at', 'assignment_ends_at',
            'assignment_percent', 'is_manager', 'supervisor_external_id', 'location_name',
            'location_address',
        ]);
    }

    /**
     * @param  array<int, string>  $seenExternalIds
     */
    private function deactivateAbsent(array $seenExternalIds): int
    {
        if ($seenExternalIds === []) {
            return 0;
        }

        return User::query()
            ->whereNotNull('external_id')
            ->where('is_active', true)
            ->whereNotIn('external_id', $seenExternalIds)
            ->update(['is_active' => false]);
    }

    /**
     * Resolve supervisorPersonId to users.id now that every person exists. Only writes
     * when the value changes, so the run stays idempotent.
     *
     * @param  array<string, User>  $processed
     */
    private function resolveSupervisors(array $processed): void
    {
        $idByExternalId = User::query()->whereNotNull('external_id')->pluck('id', 'external_id');

        foreach ($processed as $user) {
            $supervisorExternalId = $user->supervisor_external_id;
            $supervisorId = $supervisorExternalId !== null
                ? ($idByExternalId[$supervisorExternalId] ?? null)
                : null;

            if ($user->supervisor_id !== $supervisorId) {
                $user->supervisor_id = $supervisorId;
                $user->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalise(array $row): array
    {
        $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
        $primary = collect($assignments)->firstWhere('primaryFlag', 'Y') ?? [];

        return [
            'external_id' => (string) $row['personId'],
            'kennitala' => (string) $row['nationalIdentifier'],
            'display_name' => (string) $row['employeeName'],
            'email' => $this->stringOrNull($row['email'] ?? null),
            'phone' => $this->normalisePhone($row['gsm_phone'] ?? null),
            'employee_number' => $this->stringOrNull($row['employeeNumber'] ?? null),
            'person_type' => $this->stringOrNull($row['personType'] ?? null),
            'nationality' => $this->stringOrNull($row['nationality'] ?? null),
            'job_title' => $this->stringOrNull($primary['jobName'] ?? null),
            'job_id' => $this->stringOrNull($primary['jobId'] ?? null),
            'organization_id' => $this->stringOrNull($primary['organizationId'] ?? null),
            'organization_name' => $this->stringOrNull($primary['organizationName'] ?? null),
            'parent_organization_id' => $this->stringOrNull($primary['parentOrganizationId'] ?? null),
            'assignment_status' => $this->stringOrNull($primary['assignmentStatus'] ?? null),
            'assignment_starts_at' => $this->dateOrNull($primary['assignmentStartDate'] ?? null),
            'assignment_ends_at' => $this->dateOrNull($primary['assignmentEndDate'] ?? null),
            'assignment_percent' => isset($primary['assignmentPercent']) ? (int) $primary['assignmentPercent'] : null,
            'is_manager' => ($primary['managerFlag'] ?? null) === 'Y',
            'supervisor_external_id' => $this->stringOrNull($primary['supervisorPersonId'] ?? null),
            'location_name' => $this->stringOrNull($primary['locationName'] ?? null),
            'location_address' => $this->stringOrNull($primary['locationAddress'] ?? null),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * `4712-12-31` is Oracle's "no end date" sentinel — store null.
     */
    private function dateOrNull(mixed $date): ?string
    {
        if ($date === null || $date === '' || str_starts_with((string) $date, '4712-12-31')) {
            return null;
        }

        return (string) $date;
    }

    /**
     * The API returns gsm_phone as an integer; store an international string.
     */
    private function normalisePhone(mixed $gsm): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($gsm ?? ''));

        if ($digits === null || $digits === '') {
            return null;
        }

        return str_starts_with($digits, '354') ? '+'.$digits : '+354'.$digits;
    }
}
