<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\InspectionLotConfig;
use Illuminate\Support\Facades\DB;

class InspectionLotConfigService
{
    public function __construct(
        private QualityManagementService $qualityService,
    ) {}

    /**
     * Create or update the inspection lot config for a product/trigger combination.
     *
     * @param array{
     *   product_id: int,
     *   inspection_trigger: string,
     *   auto_create?: bool,
     *   sample_percentage?: float,
     *   quality_plan_id?: int|null
     * } $data
     */
    public function configureForProduct(array $data): InspectionLotConfig
    {
        $orgId = auth()->user()->organization_id;

        return DB::transaction(function () use ($data, $orgId): InspectionLotConfig {
            /** @var InspectionLotConfig $config */
            $config = InspectionLotConfig::updateOrCreate(
                [
                    'organization_id'    => $orgId,
                    'product_id'         => $data['product_id'],
                    'inspection_trigger' => $data['inspection_trigger'],
                ],
                [
                    'auto_create'       => $data['auto_create'] ?? true,
                    'sample_percentage' => $data['sample_percentage'] ?? 100,
                    'quality_plan_id'   => $data['quality_plan_id'] ?? null,
                ]
            );

            return $config->fresh(['product', 'qualityPlan']);
        });
    }

    /**
     * Automatically create an inspection lot if an active auto-create config exists
     * for the given product and trigger.
     *
     * @param int    $productId        Product to inspect
     * @param string $trigger          One of InspectionLotConfig::TRIGGER_* constants
     * @param float  $quantity         Total quantity received/produced
     * @param int    $sourceDocumentId ID of the triggering document (GR, WO, etc.)
     */
    public function autoCreateLot(
        int $productId,
        string $trigger,
        float $quantity,
        int $sourceDocumentId
    ): void {
        $orgId = auth()->user()->organization_id;

        $config = InspectionLotConfig::where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->forTrigger($trigger)
            ->autoCreate()
            ->first();

        if ($config === null) {
            return;
        }

        $sampleQty = $config->getSampleQuantity($quantity);

        $this->qualityService->createInspectionLot(
            [
                'product_id'      => $productId,
                'quality_plan_id' => $config->quality_plan_id,
                'source_type'     => $trigger,
                'source_id'       => $sourceDocumentId,
                'quantity'        => $sampleQty,
            ],
            auth()->id()
        );
    }
}
