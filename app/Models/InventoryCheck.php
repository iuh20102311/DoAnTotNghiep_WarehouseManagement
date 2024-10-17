<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCheck extends Model
{
    use HasFactory;

    protected $table = 'inventory_checks';
    protected $fillable = ['storage_area_id', 'check_date', 'status', 'note', 'created_by', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InventoryCheckDetail::class);
    }
}