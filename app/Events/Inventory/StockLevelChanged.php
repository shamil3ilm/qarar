<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Events\Concerns\HasDomainEventProperties;
use App\Events\Contracts\DomainEvent;
use App\Models\Inventory\StockLevel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLevelChanged implements DomainEvent
{
    use Dispatchable, HasDomainEventProperties, InteractsWithSockets, SerializesModels;

    public function __construct(
        public StockLevel $stockLevel,
        public float $previousQuantity,
        public float $newQuantity,
        public string $movementType,
        public ?string $referenceType = null,
        public ?int $referenceId = null
    ) {
        $this->initDomainEvent();
    }

    public function organizationId(): int
    {
        return $this->stockLevel->organization_id;
    }

    public function isLowStock(): bool
    {
        return $this->stockLevel->quantity <= $this->stockLevel->reorder_level;
    }

    public function wasAboveReorderLevel(): bool
    {
        return $this->previousQuantity > $this->stockLevel->reorder_level;
    }

    public function crossedReorderLevel(): bool
    {
        return $this->wasAboveReorderLevel() && $this->isLowStock();
    }
}
