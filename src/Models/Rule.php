<?php

namespace Vormia\ATUShipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rule extends Model
{
    protected $table = 'atu_shipping_rules';

    protected $fillable = [
        'courier_id',
        'name',
        'priority',
        'from_country',
        'to_country',
        'min_cart_subtotal',
        'max_cart_subtotal',
        'min_weight',
        'max_weight',
        'min_distance',
        'max_distance',
        'carrier_type',
        'applies_per_item',
        'tax_rate',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'min_cart_subtotal' => 'decimal:2',
        'max_cart_subtotal' => 'decimal:2',
        'min_weight' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'min_distance' => 'decimal:2',
        'max_distance' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'applies_per_item' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the courier that owns this rule.
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    /**
     * Get the fee associated with this rule.
     */
    public function fee(): HasOne
    {
        return $this->hasOne(Fee::class, 'rule_id');
    }

    /**
     * Scope to get only active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority ascending.
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
