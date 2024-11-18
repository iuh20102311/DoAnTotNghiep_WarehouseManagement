<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductImportReceiptDetail extends Model
{
    use HasFactory;
    protected $table = 'product_import_receipt_details';
    protected $fillable = ['product_id', 'product_import_receipt_id', 'storage_area_id', 'quantity', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productImportReceipt(): BelongsTo
    {
        return $this->belongsTo(ProductImportReceipt::class,'product_import_receipt_id');
    }

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class, 'storage_area_id');
    }
}