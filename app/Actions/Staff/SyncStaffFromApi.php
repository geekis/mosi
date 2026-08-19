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
 * NOTE: the payload shape (a person record with a nested `assignments` array, paginated
 * as `{ totalRows, rows }`) is assumed from the field mapping and isolated in
 * fetchAll()/normalise(). Verify it against the live API and adjust those two methods
 * only — the rest of the sync is shape-agnostic.
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
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function fetchAll(): array
    {
        $url = (string) config('services.staff_api.url');
        $token = config('services.staff_api.token');
        $fetchSize = (int) config('services.staff_api.fetch_size', 500);

        $rows = [];
        $totalRows = 0;
        $offset = 0;

        do {
            $body = Http::withToken($token)
                ->acceptJson()
                ->get($url, ['rowOffset' => $offset, 'fetchSize' => $fetchSize])
                ->throw()
                ->json();

            $page = $body['rows'] ?? [];
            $totalRows = (int) ($body['totalRows'] ?? count($page));
            $rows = array_merge($rows, $page);
            $offset += $fetchSize;
        } while (count($page) === $fetchSize && count($rows) < $totalRows);

        return [$rows, $totalRows];
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
