<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\CpqConfigurableProduct;
use App\Models\Sales\CpqConfiguration;
use App\Models\Sales\CpqConfigurationItem;
use App\Models\Sales\CpqConstraintRule;
use App\Models\Sales\CpqOption;
use App\Models\Sales\CpqOptionGroup;
use App\Models\Sales\CpqPricingRule;
use App\Models\Sales\Quotation;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CpqService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Validate constraints and return full price breakdown for a configuration.
     *
     * @param  int   $productId
     * @param  int[] $selectedOptionIds
     * @return array{errors: string[], price_breakdown: array, total_price: float}
     */
    public function configure(int $productId, array $selectedOptionIds): array
    {
        $product = CpqConfigurableProduct::findOrFail($productId);
        $errors  = $this->validateConstraints($productId, $selectedOptionIds);

        return [
            'errors'          => $errors,
            'price_breakdown' => $this->calculatePrice($productId, $selectedOptionIds),
            'product'         => [
                'id'         => $product->id,
                'name'       => $product->name,
                'base_price' => (float) $product->base_price,
                'currency'   => $product->currency_code,
            ],
        ];
    }

    /**
     * Validate CPQ constraint rules against selected options.
     *
     * @param  int   $productId
     * @param  int[] $selectedOptionIds
     * @return string[]  Validation error messages (empty = valid)
     */
    public function validateConstraints(int $productId, array $selectedOptionIds): array
    {
        $rules  = CpqConstraintRule::where('cpq_configurable_product_id', $productId)
            ->active()
            ->with(['ifOption', 'thenOption'])
            ->get();

        $errors = [];

        foreach ($rules as $rule) {
            $ifSelected   = in_array($rule->if_option_id, $selectedOptionIds, true);
            $thenSelected = in_array($rule->then_option_id, $selectedOptionIds, true);

            $violated = match ($rule->rule_type) {
                CpqConstraintRule::TYPE_REQUIRES => $ifSelected && ! $thenSelected,
                CpqConstraintRule::TYPE_EXCLUDES => $ifSelected && $thenSelected,
                CpqConstraintRule::TYPE_INCLUDES => ! $ifSelected && $thenSelected,
                default                          => false,
            };

            if ($violated) {
                $errors[] = $rule->error_message
                    ?? "Constraint violation: {$rule->rule_type} between option #{$rule->if_option_id} and #{$rule->then_option_id}";
            }
        }

        return $errors;
    }

    /**
     * Calculate price for a product + selected options, applying active pricing rules.
     *
     * @param  int   $productId
     * @param  int[] $selectedOptionIds
     * @return array{base_price: float, option_modifiers: array, rule_discounts: array, total_price: float}
     */
    public function calculatePrice(int $productId, array $selectedOptionIds): array
    {
        $product = CpqConfigurableProduct::findOrFail($productId);
        $basePrice = (float) $product->base_price;

        // Collect option modifier amounts
        $options         = CpqOption::whereIn('id', $selectedOptionIds)->active()->get();
        $optionModifiers = [];
        $modifierTotal   = 0.0;

        foreach ($options as $option) {
            $amount = $option->applyModifier($basePrice);
            $optionModifiers[] = [
                'option_id'   => $option->id,
                'option_name' => $option->name,
                'type'        => $option->price_modifier_type,
                'amount'      => $amount,
            ];
            $modifierTotal += $amount;
        }

        $priceBeforeRules = $basePrice + $modifierTotal;

        // Apply active pricing rules in priority order
        $rules         = CpqPricingRule::where('cpq_configurable_product_id', $productId)
            ->active()
            ->orderBy('priority')
            ->get();

        $ruleDiscounts = [];
        $totalDiscount = 0.0;
        $finalPrice    = $priceBeforeRules;

        foreach ($rules as $rule) {
            if (! $rule->isCurrentlyValid()) {
                continue;
            }

            if (! $this->pricingRuleConditionMet($rule, $selectedOptionIds)) {
                continue;
            }

            $discount = match ($rule->discount_type) {
                CpqPricingRule::DISCOUNT_PERCENTAGE     => $priceBeforeRules * ((float) $rule->discount_value / 100),
                CpqPricingRule::DISCOUNT_FIXED          => (float) $rule->discount_value,
                CpqPricingRule::DISCOUNT_PRICE_OVERRIDE => $priceBeforeRules - (float) $rule->discount_value,
                default                                 => 0.0,
            };

            $ruleDiscounts[] = [
                'rule_id'       => $rule->id,
                'rule_name'     => $rule->rule_name,
                'discount_type' => $rule->discount_type,
                'discount'      => $discount,
            ];

            $totalDiscount += $discount;

            // Price override rules replace the final price
            if ($rule->discount_type === CpqPricingRule::DISCOUNT_PRICE_OVERRIDE) {
                $finalPrice    = (float) $rule->discount_value;
                $totalDiscount = $priceBeforeRules - $finalPrice;
                break; // Only first matching override applies
            }
        }

        if ($totalDiscount > 0 && $ruleDiscounts !== []) {
            $finalPrice = max(0.0, $priceBeforeRules - $totalDiscount);
        }

        return [
            'base_price'       => $basePrice,
            'option_modifiers' => $optionModifiers,
            'modifier_total'   => $modifierTotal,
            'price_before_rules' => $priceBeforeRules,
            'rule_discounts'   => $ruleDiscounts,
            'total_discount'   => $totalDiscount,
            'total_price'      => round($finalPrice, 4),
        ];
    }

    /**
     * Persist a CPQ configuration.
     *
     * @param  array{
     *     organization_id: int,
     *     cpq_configurable_product_id: int,
     *     contact_id?: int,
     *     selected_options: array<array{option_id: int, option_group_id: int, quantity?: float}>,
     *     currency_code?: string,
     *     created_by?: int
     * } $data
     */
    public function saveConfiguration(array $data): CpqConfiguration
    {
        return DB::transaction(function () use ($data): CpqConfiguration {
            $product = CpqConfigurableProduct::findOrFail($data['cpq_configurable_product_id']);

            $selectedOptionIds = array_column($data['selected_options'], 'option_id');
            $priceBreakdown    = $this->calculatePrice($product->id, $selectedOptionIds);

            $validUntil = now()->addDays($product->configuration_validity_days)->toDateString();
            $code       = $this->numberGenerator->generate('CPQ');

            $configuration = CpqConfiguration::create([
                'organization_id'            => $data['organization_id'],
                'cpq_configurable_product_id' => $product->id,
                'contact_id'                 => $data['contact_id'] ?? null,
                'configuration_code'         => $code,
                'status'                     => CpqConfiguration::STATUS_VALID,
                'total_price'               => $priceBreakdown['total_price'],
                'currency_code'              => $data['currency_code'] ?? $product->currency_code,
                'valid_until'               => $validUntil,
                'created_by'                => $data['created_by'] ?? null,
            ]);

            foreach ($data['selected_options'] as $selected) {
                $option    = CpqOption::findOrFail($selected['option_id']);
                $quantity  = (float) ($selected['quantity'] ?? 1);
                $unitPrice = $option->applyModifier((float) $product->base_price);
                $lineTotal = $unitPrice * $quantity;

                CpqConfigurationItem::create([
                    'cpq_configuration_id' => $configuration->id,
                    'cpq_option_group_id'  => $selected['option_group_id'],
                    'cpq_option_id'        => $option->id,
                    'quantity'             => $quantity,
                    'unit_price'           => $unitPrice,
                    'line_total'           => $lineTotal,
                ]);
            }

            return $configuration->load(['items.option', 'items.optionGroup']);
        });
    }

    /**
     * Convert a CPQ configuration to a Quotation.
     *
     * @param  array{
     *     salesperson_id?: int,
     *     notes?: string,
     *     terms_and_conditions?: string
     * } $quotationData
     */
    public function convertToQuotation(CpqConfiguration $config, array $quotationData): Quotation
    {
        if (! $config->canConvert()) {
            throw new \RuntimeException("Configuration {$config->configuration_code} cannot be converted.");
        }

        return DB::transaction(function () use ($config, $quotationData): Quotation {
            $product         = $config->configurableProduct()->with('product')->firstOrFail();
            $quotationNumber = $this->numberGenerator->generate('QUO');

            $quotation = Quotation::create([
                'organization_id'  => $config->organization_id,
                'quotation_number' => $quotationNumber,
                'customer_id'      => $config->contact_id,
                'quotation_date'   => now()->toDateString(),
                'valid_until'      => $config->valid_until?->toDateString(),
                'currency_code'    => $config->currency_code,
                'exchange_rate'    => 1,
                'subtotal'         => $config->total_price,
                'tax_amount'       => 0,
                'total'            => $config->total_price,
                'status'           => Quotation::STATUS_DRAFT,
                'salesperson_id'   => $quotationData['salesperson_id'] ?? null,
                'notes'            => $quotationData['notes'] ?? null,
                'terms_and_conditions' => $quotationData['terms_and_conditions'] ?? null,
                'created_by'       => auth()->id(),
            ]);

            // Create a single quotation line for the configured product
            $quotation->lines()->create([
                'product_id'   => $product->product_id,
                'description'  => "Configured: {$config->configurableProduct->name} ({$config->configuration_code})",
                'quantity'     => 1,
                'unit_price'   => $config->total_price,
                'subtotal'     => $config->total_price,
                'tax_amount'   => 0,
                'total'        => $config->total_price,
                'line_order'   => 1,
            ]);

            // Mark configuration as converted and link quotation
            $config->update([
                'status'       => CpqConfiguration::STATUS_CONVERTED,
                'quotation_id' => $quotation->id,
            ]);

            return $quotation->load('lines');
        });
    }

    /**
     * Get all active configurable products for an organisation.
     */
    public function getConfigurableProducts(int $organizationId): Collection
    {
        return CpqConfigurableProduct::where('organization_id', $organizationId)
            ->active()
            ->with(['product', 'optionGroups.options'])
            ->get();
    }

    /**
     * Check whether a pricing rule's condition JSON is satisfied by the selected options.
     * The condition JSON supports: {"required_options": [1, 2], "min_options": 3}
     *
     * @param  int[] $selectedOptionIds
     */
    private function pricingRuleConditionMet(CpqPricingRule $rule, array $selectedOptionIds): bool
    {
        $condition = $rule->condition_json;

        if (empty($condition)) {
            return true;
        }

        // required_options: all listed option IDs must be selected
        if (isset($condition['required_options']) && is_array($condition['required_options'])) {
            foreach ($condition['required_options'] as $requiredId) {
                if (! in_array((int) $requiredId, $selectedOptionIds, true)) {
                    return false;
                }
            }
        }

        // any_of_options: at least one of the listed option IDs must be selected
        if (isset($condition['any_of_options']) && is_array($condition['any_of_options'])) {
            $found = false;
            foreach ($condition['any_of_options'] as $anyId) {
                if (in_array((int) $anyId, $selectedOptionIds, true)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        // min_options: minimum number of selected options
        if (isset($condition['min_options']) && count($selectedOptionIds) < (int) $condition['min_options']) {
            return false;
        }

        return true;
    }
}
