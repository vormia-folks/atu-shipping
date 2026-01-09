<?php

namespace Vormia\ATUShipping\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'atu_shipping_logs';

    protected $fillable = [
        'courier_id',
        'rule_id',
        'order_id',
        'cart_subtotal',
        'total_weight',
        'from_country',
        'to_country',
        'shipping_fee',
        'shipping_tax',
        'shipping_total',
        'currency',
        'tax_rate',
        'context',
    ];

    protected $casts = [
        'cart_subtotal' => 'decimal:2',
        'total_weight' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'context' => 'array',
    ];
}
