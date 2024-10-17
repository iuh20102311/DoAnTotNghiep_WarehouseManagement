<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftSetProduct extends Model
{
    use HasFactory;

    protected $table = 'gift_set_products';
    protected $fillable = ['gift_set_id', 'product_id', 'quantity', 'created_at', 'updated_at', 'deleted'];
    public $incrementing = false;
    protected $primaryKey = ['gift_set_id', 'product_id'];
    public $timestamps = true;

    public function giftSet(): BelongsTo
    {
        return $this->belongsTo(GiftSet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}