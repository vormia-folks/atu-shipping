<?php

namespace Vormia\ATUShipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fee extends Model
{
    protected $table = 'atu_shipping_fees';

    protected $fillable = [
        'rule_id',
        'fee_type',
        'flat_fee',
        'per_kg_fee',
    ];

    protected $casts = [
        'flat_fee' => 'decimal:2',
        'per_kg_fee' => 'decimal:2',
    ];

    /**
     * Fee type constants.
     */
    public const TYPE_FLAT = 'flat';
    public const TYPE_PER_KG = 'per_kg';

    /**
     * Get the rule that owns this fee.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'rule_id');
    }
}
