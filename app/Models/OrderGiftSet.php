<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderGiftSet extends Model
{
    protected $fillable = [
        'gift_set_id',
        'order_id',
        'quantity',
        'price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
        'deleted' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function giftSet(): BelongsTo
    {
        return $this->belongsTo(GiftSet::class);
    }
}