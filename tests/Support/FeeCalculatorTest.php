<?php

namespace Vormia\ATUShipping\Tests\Support;

use PHPUnit\Framework\TestCase;
use Vormia\ATUShipping\Contracts\CartInterface;
use Vormia\ATUShipping\Models\Fee;
use Vormia\ATUShipping\Models\Rule;
use Vormia\ATUShipping\Support\FeeCalculator;

/**
 * Pure-logic test for FeeCalculator. We construct real Rule + Fee Eloquent
 * models (which instantiate without a database connection) and preload the
 * `fee` relation by hand so the calculator never queries.
 */
class FeeCalculatorTest extends TestCase
{
    private FeeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new FeeCalculator();
    }

    public function test_flat_fee_uses_flat_amount_regardless_of_weight(): void
    {
        $rule = $this->makeRule(feeType: Fee::TYPE_FLAT, flat: 100.00, perKg: 5.00);
        $cart = $this->makeCart(weight: 10, items: [['weight' => 1, 'quantity' => 10]]);

        $result = $this->calculator->calculate($rule, $cart);

        $this->assertSame(100.0, $result['fee']);
        $this->assertSame(0.0, $result['tax']);
        $this->assertSame(100.0, $result['total']);
    }

    public function test_per_kg_fee_uses_total_cart_weight_by_default(): void
    {
        $rule = $this->makeRule(feeType: Fee::TYPE_PER_KG, flat: 0, perKg: 7.50, appliesPerItem: false);
        $cart = $this->makeCart(weight: 4, items: []);

        $result = $this->calculator->calculate($rule, $cart);

        $this->assertSame(30.0, $result['fee'], '4kg * 7.50/kg = 30.00');
    }

    public function test_per_kg_fee_with_applies_per_item_uses_each_line(): void
    {
        $rule = $this->makeRule(feeType: Fee::TYPE_PER_KG, flat: 0, perKg: 10.00, appliesPerItem: true);
        $cart = $this->makeCart(weight: 999, items: [
            ['weight' => 2, 'quantity' => 3],   // 2 * 3 * 10 = 60
            ['weight' => 1.5, 'quantity' => 2], // 1.5 * 2 * 10 = 30
        ]);

        $result = $this->calculator->calculate($rule, $cart);

        $this->assertSame(90.0, $result['fee'], 'Per-item: ignores total weight, uses items.');
    }

    public function test_tax_rate_is_applied_to_base_fee(): void
    {
        $rule = $this->makeRule(feeType: Fee::TYPE_FLAT, flat: 200.00, perKg: 0, taxRate: 0.16);
        $cart = $this->makeCart(weight: 0, items: []);

        $result = $this->calculator->calculate($rule, $cart);

        $this->assertSame(200.0, $result['fee']);
        $this->assertSame(32.0, $result['tax'], '200 * 0.16 = 32.00');
        $this->assertSame(232.0, $result['total']);
        $this->assertEqualsWithDelta(0.16, (float) $result['tax_rate'], 0.0001);
    }

    public function test_rule_currency_is_used_when_set(): void
    {
        $rule = $this->makeRule(feeType: Fee::TYPE_FLAT, flat: 1, perKg: 0, currency: 'ZAR');
        $cart = $this->makeCart(weight: 0, items: []);

        $result = $this->calculator->calculate($rule, $cart);

        $this->assertSame('ZAR', $result['currency']);
    }

    public function test_fee_is_zero_when_rule_has_no_attached_fee(): void
    {
        $rule = $this->makeRuleWithoutFee();
        $cart = $this->makeCart(weight: 5, items: []);

        $result = $this->calculator->calculate($rule, $cart);

        $this->assertSame(0.0, $result['fee']);
        $this->assertSame(0.0, $result['total']);
    }

    public function test_results_are_rounded_to_two_decimals(): void
    {
        // per_kg = 1.00 (clean after decimal:2 cast); weight = 3.333 (float).
        // Product = 3.333 which the calculator must round() to 3.33.
        $rule = $this->makeRule(feeType: Fee::TYPE_PER_KG, flat: 0, perKg: 1.00, appliesPerItem: false);
        $cart = $this->makeCart(weight: 3.333, items: []);

        $result = $this->calculator->calculate($rule, $cart);

        $this->assertSame(3.33, $result['fee']);
    }

    // ---------------------------------------------------------------------
    // Test doubles
    // ---------------------------------------------------------------------

    private function makeRule(
        string $feeType,
        float $flat,
        float $perKg,
        bool $appliesPerItem = false,
        ?float $taxRate = null,
        ?string $currency = 'USD'
    ): Rule {
        $fee = new Fee();
        $fee->forceFill([
            'fee_type'   => $feeType,
            'flat_fee'   => $flat,
            'per_kg_fee' => $perKg,
        ]);

        $rule = new Rule();
        $rule->forceFill([
            'currency'         => $currency,
            'tax_rate'         => $taxRate,
            'applies_per_item' => $appliesPerItem,
        ]);
        // Preload the relation so the calculator never queries the DB.
        $rule->setRelation('fee', $fee);

        return $rule;
    }

    private function makeRuleWithoutFee(): Rule
    {
        $rule = new Rule();
        $rule->forceFill([
            'currency'         => 'USD',
            'tax_rate'         => null,
            'applies_per_item' => false,
        ]);
        $rule->setRelation('fee', null);

        return $rule;
    }

    private function makeCart(float $weight, array $items, float $subtotal = 0.0): CartInterface
    {
        return new class($weight, $subtotal, $items) implements CartInterface {
            public function __construct(
                private float $weight,
                private float $subtotal,
                private array $items
            ) {}

            public function getSubtotal(): float
            {
                return $this->subtotal;
            }

            public function getTotalWeight(): float
            {
                return $this->weight;
            }

            public function getItems(): array
            {
                return $this->items;
            }
        };
    }
}
