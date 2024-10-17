<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryHistory extends Model
{
    use HasFactory;

    protected $table = 'inventory_history';
    protected $fillable = ['storage_area_id', 'product_id', 'material_id', 'quantity_before', 'quantity_change', 'quantity_after', 'remaining_quantity', 'action_type', 'reference_id', 'reference_type', 'created_at', 'created_by'];
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}