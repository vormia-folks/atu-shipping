<?php

namespace Vormia\ATUShipping\Support;

use Illuminate\Support\Facades\Log as LaravelLog;
use Vormia\ATUShipping\Contracts\CartInterface;
use Vormia\ATUShipping\Contracts\OrderInterface;
use Vormia\ATUShipping\Models\Courier;
use Vormia\ATUShipping\Models\Log as ShippingLog;
use Vormia\ATUShipping\Models\Rule;

class ShippingService
{
    protected ?CartInterface $cart = null;
    protected ?OrderInterface $order = null;
    protected ?string $fromCountry = null;
    protected ?string $toCountry = null;

    public function __construct(
        protected RuleEvaluator $ruleEvaluator,
        protected FeeCalculator $feeCalculator
    ) {}

    /**
     * Bind cart context.
     *
     * @param CartInterface $cart
     * @return $this
     */
    public function forCart(CartInterface $cart): self
    {
        $this->cart = $cart;
        $this->order = null;
        return $this;
    }

    /**
     * Bind order context.
     *
     * @param OrderInterface $order
     * @return $this
     */
    public function forOrder(OrderInterface $order): self
    {
        $this->order = $order;
        $this->cart = null;
        
        // Auto-set countries from order if available
        if ($order->getOriginCountry()) {
            $this->fromCountry = $order->getOriginCountry();
        }
        if ($order->getDestinationCountry()) {
            $this->toCountry = $order->getDestinationCountry();
        }
        
        return $this;
    }

    /**
     * Set origin country.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     * @return $this
     */
    public function from(string $countryCode): self
    {
        $this->fromCountry = $countryCode;
        return $this;
    }

    /**
     * Set destination country.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     * @return $this
     */
    public function to(string $countryCode): self
    {
        $this->toCountry = $countryCode;
        return $this;
    }

    /**
     * Get available shipping options.
     *
     * @return array<int, array{courier: string, fee: float, tax: float, total: float, currency: string, rule_id: int, tax_rate: float|null}>
     */
    public function options(): array
    {
        $context = $this->getContext();
        
        if ($context === null) {
            return [];
        }

        if ($this->fromCountry === null || $this->toCountry === null) {
            return [];
        }

        $matchingRules = $this->ruleEvaluator->evaluateRules(
            $context,
            $this->fromCountry,
            $this->toCountry
        );

        $options = [];

        foreach ($matchingRules as $courierId => $rule) {
            $courier = Courier::find($courierId);
            if ($courier === null) {
                continue;
            }

            $calculation = $this->feeCalculator->calculate($rule, $context);

            $options[] = [
                'courier' => $courier->name,
                'fee' => $calculation['fee'],
                'tax' => $calculation['tax'],
                'total' => $calculation['total'],
                'currency' => $calculation['currency'],
                'rule_id' => $rule->id,
                'tax_rate' => $calculation['tax_rate'],
            ];
        }

        return $options;
    }

    /**
     * Select a courier and finalize shipping (logs the selection).
     *
     * @param string $courierName
     * @return array{fee: float, tax: float, total: float, currency: string}|null
     */
    public function select(string $courierName): ?array
    {
        $context = $this->getContext();
        
        if ($context === null) {
            return null;
        }

        if ($this->fromCountry === null || $this->toCountry === null) {
            return null;
        }

        $courier = Courier::where('name', $courierName)->active()->first();
        if ($courier === null) {
            return null;
        }

        $matchingRules = $this->ruleEvaluator->evaluateRules(
            $context,
            $this->fromCountry,
            $this->toCountry
        );

        $rule = $matchingRules[$courier->id] ?? null;
        if ($rule === null) {
            return null;
        }

        $calculation = $this->feeCalculator->calculate($rule, $context);

        // Log the selection
        $this->logSelection($courier, $rule, $context, $calculation);

        return [
            'fee' => $calculation['fee'],
            'tax' => $calculation['tax'],
            'total' => $calculation['total'],
            'currency' => $calculation['currency'],
        ];
    }

    /**
     * Get the current context (cart or order).
     *
     * @return CartInterface|OrderInterface|null
     */
    protected function getContext()
    {
        return $this->order ?? $this->cart;
    }

    /**
     * Log shipping selection.
     *
     * @param Courier $courier
     * @param Rule $rule
     * @param CartInterface|OrderInterface $context
     * @param array $calculation
     * @return void
     */
    protected function logSelection(Courier $courier, Rule $rule, $context, array $calculation): void
    {
        try {
            $orderId = null;
            if ($context instanceof OrderInterface) {
                // Try to get order ID if available
                if (method_exists($context, 'getId')) {
                    $orderId = $context->getId();
                } elseif (property_exists($context, 'id')) {
                    $orderId = $context->id;
                }
            }

            ShippingLog::create([
                'courier_id' => $courier->id,
                'rule_id' => $rule->id,
                'order_id' => $orderId,
                'cart_subtotal' => $context->getSubtotal(),
                'total_weight' => $context->getTotalWeight(),
                'from_country' => $this->fromCountry,
                'to_country' => $this->toCountry,
                'shipping_fee' => $calculation['fee'],
                'shipping_tax' => $calculation['tax'],
                'shipping_total' => $calculation['total'],
                'currency' => $calculation['currency'],
                'tax_rate' => $calculation['tax_rate'],
                'context' => [
                    'type' => $this->order ? 'order' : 'cart',
                ],
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail
            LaravelLog::error('Failed to log shipping selection: ' . $e->getMessage());
        }
    }
}
