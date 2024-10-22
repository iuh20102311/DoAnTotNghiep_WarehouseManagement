<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderMaterial extends Model
{
    protected $fillable = [
        'provider_id',
        'material_id',
    ];

    protected $casts = [
        'deleted' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}