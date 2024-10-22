<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCheckDetail extends Model
{
    use HasFactory;

    protected $table = 'inventory_check_details';
    protected $fillable = ['product_id', 'inventory_check_id', 'material_id', 'exact_quantity', 'actual_quantity', 'defective_quantity', 'error_description', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function inventoryCheck(): BelongsTo
    {
        return $this->belongsTo(InventoryCheck::class, 'inventory_check_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    // Mutator to enforce constraint programmatically
    public function setProductIdAttribute($value)
    {
        if (!is_null($value)) {
            $this->attributes['product_id'] = $value;
            $this->attributes['material_id'] = null; // Force material_id to null
        }
    }

    public function setMaterialIdAttribute($value)
    {
        if (!is_null($value)) {
            $this->attributes['material_id'] = $value;
            $this->attributes['product_id'] = null; // Force product_id to null
        }
    }
}
