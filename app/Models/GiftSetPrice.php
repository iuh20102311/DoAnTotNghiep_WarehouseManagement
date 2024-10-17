<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftSetPrice extends Model
{
    use HasFactory;

    protected $table = 'gift_set_prices';
    protected $fillable = ['gift_set_id', 'date_expiry', 'price', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function giftSet(): BelongsTo
    {
        return $this->belongsTo(GiftSet::class);
    }
}