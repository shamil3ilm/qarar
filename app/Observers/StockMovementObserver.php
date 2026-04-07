<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateStockLevelSnapshotJob;
use App\Models\Inventory\StockMovement;

class StockMovementObserver
{
    /**
     * Dispatch a snapshot-update job after a stock movement is recorded.
     */
    public function created(StockMovement $movement): void
    {
        $this->dispatchSnapshot($movement);
    }

    public function updated(StockMovement $movement): void
    {
        $this->dispatchSnapshot($movement);
    }

    public function deleted(StockMovement $movement): void
    {
        $this->dispatchSnapshot($movement);
    }

    private function dispatchSnapshot(StockMovement $movement): void
    {
        UpdateStockLevelSnapshotJob::dispatch(
            $movement->organization_id,
            $movement->product_id,
            $movement->warehouse_id,
        );
    }
}
