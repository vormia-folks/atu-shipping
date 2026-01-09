<?php

namespace Vormia\ATUShipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    protected $table = 'atu_shipping_couriers';

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all rules for this courier.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class, 'courier_id');
    }

    /**
     * Scope to get only active couriers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
