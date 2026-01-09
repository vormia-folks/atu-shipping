<?php

namespace Vormia\ATUShipping\Support;

use Vormia\ATUShipping\Contracts\CartInterface;
use Vormia\ATUShipping\Contracts\OrderInterface;
use Vormia\ATUShipping\Models\Fee;
use Vormia\ATUShipping\Models\Rule;

class FeeCalculator
{
    /**
     * Calculate shipping fee and tax for a rule.
     *
     * @param Rule $rule
     * @param CartInterface|OrderInterface $context
     * @return array{fee: float, tax: float, total: float, currency: string, tax_rate: float|null}
     */
    public function calculate(Rule $rule, $context): array
    {
        $fee = $this->calculateBaseFee($rule, $context);
        $currency = $rule->currency ?? config('a2_commerce.currency', 'USD');
        $taxRate = $rule->tax_rate ?? 0;
        $tax = $fee * $taxRate;
        $total = $fee + $tax;

        return [
            'fee' => round($fee, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'currency' => $currency,
            'tax_rate' => $taxRate,
        ];
    }

    /**
     * Calculate base shipping fee (before tax).
     *
     * @param Rule $rule
     * @param CartInterface|OrderInterface $context
     * @return float
     */
    protected function calculateBaseFee(Rule $rule, $context): float
    {
        // Load the fee relationship if not already loaded
        if (!$rule->relationLoaded('fee')) {
            $rule->load('fee');
        }

        $fee = $rule->fee;

        if ($fee === null) {
            return 0.0;
        }

        if ($fee->fee_type === Fee::TYPE_FLAT) {
            return (float) $fee->flat_fee;
        }

        // Per-kg fee
        if ($rule->applies_per_item) {
            // Calculate per item
            $totalFee = 0.0;
            foreach ($context->getItems() as $item) {
                $itemWeight = $item['weight'] ?? 0;
                $quantity = $item['quantity'] ?? 1;
                $totalFee += ($itemWeight * $quantity * (float) $fee->per_kg_fee);
            }
            return $totalFee;
        }

        // Use total cart weight
        $totalWeight = $context->getTotalWeight();
        return $totalWeight * (float) $fee->per_kg_fee;
    }

    /**
     * Convert currency if ATU Multi-Currency is available.
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        // Check if ATU Multi-Currency is available
        if (!class_exists(\Vormia\ATUMultiCurrency\Support\CurrencySyncService::class)) {
            return $amount;
        }

        // If currencies are the same, no conversion needed
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        // Attempt to use ATU Multi-Currency conversion
        try {
            // This would need to be implemented based on ATU Multi-Currency API
            // For now, return the original amount
            return $amount;
        } catch (\Exception $e) {
            // If conversion fails, return original amount
            return $amount;
        }
    }
}
