<?php

namespace App\Console\Commands;

use App\Actions\Staff\SyncStaffFromApi;
use App\Models\StaffSyncRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-staff')]
#[Description('Sync users from the NVS staff API (the sole source of accounts).')]
class SyncStaffCommand extends Command
{
    public function handle(SyncStaffFromApi $sync): int
    {
        $run = $sync();

        $this->table(
            ['Status', 'Total', 'Created', 'Updated', 'Reactivated', 'Deactivated'],
            [[
                $run->status,
                $run->total_rows,
                $run->created_count,
                $run->updated_count,
                $run->reactivated_count,
                $run->deactivated_count,
            ]],
        );

        return match ($run->status) {
            StaffSyncRun::STATUS_SUCCESS => self::SUCCESS,
            StaffSyncRun::STATUS_ABORTED => self::INVALID,
            default => self::FAILURE,
        };
    }
}
