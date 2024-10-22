<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftSet extends Model
{
    use HasFactory;

    protected $table = 'gift_sets';
    protected $fillable = ['name', 'description', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'gift_set_products')->withPivot('quantity');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(GiftSetPrice::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_gift_sets')->withPivot('quantity', 'price');
    }

    // Quan hệ bảng nhiều nhiều
    public function giftSetProducts(): HasMany
    {
        return $this->hasMany(GiftSetProduct::class);
    }

    public function orderGiftSets(): HasMany
    {
        return $this->hasMany(OrderGiftSet::class);
    }
}