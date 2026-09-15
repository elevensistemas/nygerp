<?php

namespace App\Observers;

use App\Models\DriverLogisticsRecord;
use App\Services\DriverPayments\DriverLogisticsAutoSyncService;

class DriverLogisticsRecordObserver
{
    public function created(DriverLogisticsRecord $record): void
    {
        if (DriverLogisticsAutoSyncService::$isSyncing) {
            return;
        }

        app(DriverLogisticsAutoSyncService::class)->syncByRecordIds([$record->id], []);
    }

    public function updated(DriverLogisticsRecord $record): void
    {
        if (DriverLogisticsAutoSyncService::$isSyncing) {
            return;
        }

        app(DriverLogisticsAutoSyncService::class)->syncByRecordIds([$record->id], []);
    }

    public function deleted(DriverLogisticsRecord $record): void
    {
        if (DriverLogisticsAutoSyncService::$isSyncing) {
            return;
        }

        app(DriverLogisticsAutoSyncService::class)->syncByRecordIds([], [$record->id]);
    }
}
