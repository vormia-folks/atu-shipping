<?php

namespace Vormia\ATUShipping\Contracts;

interface OrderInterface
{
    /**
     * Get order subtotal in base currency.
     */
    public function getSubtotal(): float;

    /**
     * Get total weight of all items in order.
     */
    public function getTotalWeight(): float;

    /**
     * Get order items.
     *
     * @return array<int, array{weight: float, quantity: int, origin_country?: string}>
     */
    public function getItems(): array;

    /**
     * Get destination country code.
     */
    public function getDestinationCountry(): ?string;

    /**
     * Get origin country code.
     */
    public function getOriginCountry(): ?string;
}
