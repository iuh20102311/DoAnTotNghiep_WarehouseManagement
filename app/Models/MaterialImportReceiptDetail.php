<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaterialImportReceiptDetail extends Model
{
    use HasFactory;
    protected $table = 'material_import_receipt_details';
    protected $fillable = ['material_id', 'material_import_receipt_id', 'material_storage_location_id', 'quantity', 'price', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function materialImportReceipt(): BelongsTo
    {
        return $this->belongsTo(MaterialImportReceipt::class,'material_import_receipt_id');
    }

    public function materialStorageLocation(): BelongsTo
    {
        return $this->belongsTo(MaterialStorageLocation::class, 'material_storage_location_id');
    }
}