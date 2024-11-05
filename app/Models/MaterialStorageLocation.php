<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStorageLocation extends Model
{
    use HasFactory;

    protected $table = 'material_storage_locations';
    protected $fillable = ['material_id', 'provider_id','storage_area_id', 'quantity', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'material_id' => ['required', 'integer'],
            'provider_id' => ['required', 'integer'],
            'storage_area_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min' => 0],
        ];

        if ($isUpdate) {
            $rules = array_intersect_key($rules, $data);

            // Bỏ qua validate required
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        if (isset($data['material_id']) && !Material::where('deleted', false)->find($data['material_id'])) {
            return ['material_id' => ['Vật liệu không tồn tại']];
        }

        if (isset($data['provider_id']) && !Provider::where('deleted', false)->find($data['provider_id'])) {
            return ['provider_id' => ['Nhà cung cấp không tồn tại']];
        }

        if (isset($data['storage_area_id']) && !StorageArea::where('deleted', false)->find($data['storage_area_id'])) {
            return ['storage_area_id' => ['Khu vực kho không tồn tại']];
        }

        return null;
    }

    protected function messages()
    {
        return [
            'material_id' => [
                'required' => 'ID vật liệu là bắt buộc.',
                'integer' => 'ID vật liệu phải là số nguyên.'
            ],
            'provider_id' => [
                'required' => 'ID nhà cung cấp là bắt buộc.',
                'integer' => 'ID nhà cung cấp phải là số nguyên.'
            ],
            'storage_area_id' => [
                'required' => 'ID khu vực kho là bắt buộc.',
                'integer' => 'ID khu vực kho phải là số nguyên.'
            ],
            'quantity' => [
                'required' => 'Số lượng là bắt buộc.',
                'integer' => 'Số lượng phải là số nguyên.',
                'min' => 'Số lượng không được nhỏ hơn :min.'
            ]
        ];
    }
}