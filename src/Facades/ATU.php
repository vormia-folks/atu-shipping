<?php

namespace Vormia\ATUShipping\Facades;

use Vormia\ATUShipping\Support\ShippingService;

/**
 * ATU Facade
 *
 * Provides static access to ATU services.
 * This facade can be extended by other ATU packages.
 */
class ATU
{
    /**
     * Get the shipping service instance.
     *
     * @return ShippingService
     */
    public static function shipping(): ShippingService
    {
        return app(ShippingService::class);
    }
}
