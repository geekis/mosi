<?php

namespace App\Filament\Pages;

use App\Models\StaffSyncRun;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Manual staff-sync trigger and run log. Restricted to super-admins — the sync creates,
 * updates and deactivates accounts, so it is the most sensitive action in the panel.
 */
class StaffSync extends Page
{
    protected string $view = 'filament.pages.staff-sync';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->hasAnyRoleName('super-admin');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label(__('Run staff sync now'))
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('app:sync-staff');

                    Notification::make()
                        ->title(__('Staff sync started.'))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return Collection<int, StaffSyncRun>
     */
    public function recentRuns()
    {
        return StaffSyncRun::query()->latest()->limit(20)->get();
    }
}
