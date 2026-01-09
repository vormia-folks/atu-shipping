<?php

namespace Vormia\ATUShipping\Support;

use Vormia\ATUShipping\Contracts\CartInterface;
use Vormia\ATUShipping\Contracts\OrderInterface;
use Vormia\ATUShipping\Models\Courier;
use Vormia\ATUShipping\Models\Rule;

class RuleEvaluator
{
    /**
     * Evaluate rules for all active couriers and return matching rules.
     *
     * @param CartInterface|OrderInterface $context
     * @param string $fromCountry Origin country code
     * @param string $toCountry Destination country code
     * @return array<int, Rule> Array of matching rules keyed by courier_id
     */
    public function evaluateRules($context, string $fromCountry, string $toCountry): array
    {
        $matches = [];
        $activeCouriers = Courier::active()->get();

        foreach ($activeCouriers as $courier) {
            $rule = $this->findMatchingRule($courier, $context, $fromCountry, $toCountry);
            if ($rule !== null) {
                $matches[$courier->id] = $rule;
            }
        }

        return $matches;
    }

    /**
     * Find the first matching rule for a courier.
     *
     * @param Courier $courier
     * @param CartInterface|OrderInterface $context
     * @param string $fromCountry
     * @param string $toCountry
     * @return Rule|null
     */
    protected function findMatchingRule(Courier $courier, $context, string $fromCountry, string $toCountry): ?Rule
    {
        $rules = Rule::where('courier_id', $courier->id)
            ->active()
            ->orderedByPriority()
            ->with('fee')
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $context, $fromCountry, $toCountry)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Check if a rule matches the given context.
     *
     * @param Rule $rule
     * @param CartInterface|OrderInterface $context
     * @param string $fromCountry
     * @param string $toCountry
     * @return bool
     */
    protected function ruleMatches(Rule $rule, $context, string $fromCountry, string $toCountry): bool
    {
        // Check country constraints
        if ($rule->from_country !== null && $rule->from_country !== $fromCountry) {
            return false;
        }

        if ($rule->to_country !== null && $rule->to_country !== $toCountry) {
            return false;
        }

        // Get context values
        $subtotal = $context->getSubtotal();
        $totalWeight = $context->getTotalWeight();

        // Check cart subtotal constraints
        if ($rule->min_cart_subtotal !== null && $subtotal < $rule->min_cart_subtotal) {
            return false;
        }

        if ($rule->max_cart_subtotal !== null && $subtotal > $rule->max_cart_subtotal) {
            return false;
        }

        // Check weight constraints
        if ($rule->min_weight !== null && $totalWeight < $rule->min_weight) {
            return false;
        }

        if ($rule->max_weight !== null && $totalWeight > $rule->max_weight) {
            return false;
        }

        // Note: Distance and carrier_type constraints are optional and not evaluated here
        // as they require additional context that may not be available

        return true;
    }
}
