<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class MaterialStorageLocation extends Model
{
    use HasFactory;

    protected $table = 'material_storage_locations';
    protected $fillable = ['material_id', 'storage_area_id', 'quantity', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }
}