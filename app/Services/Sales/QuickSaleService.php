<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\QuickSaleTemplate;
use Illuminate\Support\Facades\DB;

class QuickSaleService
{
    public function __construct() {}

    /**
     * Create a quick sale template.
     */
    public function createTemplate(array $data): QuickSaleTemplate
    {
        return DB::transaction(function () use ($data) {
            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['is_active'] = $data['is_active'] ?? true;

            return QuickSaleTemplate::create($data);
        });
    }

    /**
     * Use a template to pre-populate a bulk sale batch.
     */
    public function useTemplate(QuickSaleTemplate $template): array
    {
        if (!$template->is_active) {
            throw new \InvalidArgumentException('Template is inactive.');
        }

        return [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'default_customer_id' => $template->default_customer_id,
            'default_payment_method' => $template->default_payment_method,
            'items' => $template->default_items ?? [],
        ];
    }

    /**
     * Duplicate a template.
     */
    public function duplicateTemplate(QuickSaleTemplate $template, ?string $newName = null): QuickSaleTemplate
    {
        return DB::transaction(function () use ($template, $newName) {
            $data = $template->toArray();

            unset($data['id'], $data['created_at'], $data['updated_at']);

            $data['name'] = $newName ?? $template->name . ' (Copy)';

            return QuickSaleTemplate::create($data);
        });
    }
}
