<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;
     protected $fillable = [
        'code',
        'type',
        'value',
        'minimum_cart_amount',
        'maximum_discount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'per_customer_usage_limit',
        'active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function usages(): HasMany
{
    return $this->hasMany(PromotionUsage::class);
}
}
