<?php

namespace Vormia\ATUShipping\Contracts;

interface CartInterface
{
    /**
     * Get cart subtotal in base currency.
     */
    public function getSubtotal(): float;

    /**
     * Get total weight of all items in cart.
     */
    public function getTotalWeight(): float;

    /**
     * Get cart items.
     *
     * @return array<int, array{weight: float, quantity: int, origin_country?: string}>
     */
    public function getItems(): array;
}
