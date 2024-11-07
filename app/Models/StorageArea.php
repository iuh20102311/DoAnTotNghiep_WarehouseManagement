<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageArea extends Model
{
    use HasFactory;

    protected $table = 'storage_areas';
    protected $fillable = ['name', 'code', 'description', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function productStorageLocations(): HasMany
    {
        return $this->hasMany(ProductStorageLocation::class);
    }

    public function materialStorageLocations(): HasMany
    {
        return $this->hasMany(MaterialStorageLocation::class);
    }

    public function inventoryChecks(): HasMany
    {
        return $this->hasMany(InventoryCheck::class);
    }

    public function inventoryHistory(): HasMany
    {
        return $this->hasMany(InventoryHistory::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'max' => 255],
            'code' => ['required', 'max' => 50],
            'description' => ['max' => 1000],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE']]
        ];

        if ($isUpdate) {
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        // Validate unique code
        if (isset($data['code'])) {
            $query = self::where('code', $data['code'])
                ->where('deleted', false);

            if ($isUpdate) {
                $query->where('id', '!=', $this->id);
            }

            if ($query->exists()) {
                return ['code' => ['Mã khu vực kho đã tồn tại']];
            }
        }

        // Validate unique name
        if (isset($data['name'])) {
            $query = self::where('name', $data['name'])
                ->where('deleted', false);

            if ($isUpdate) {
                $query->where('id', '!=', $this->id);
            }

            if ($query->exists()) {
                return ['name' => ['Tên khu vực kho đã tồn tại']];
            }
        }

        return null;
    }

    protected function messages()
    {
        return [
            'name' => [
                'required' => 'Tên khu vực kho là bắt buộc.',
                'max' => 'Tên khu vực kho không được vượt quá :max ký tự.'
            ],
            'code' => [
                'required' => 'Mã khu vực kho là bắt buộc.',
                'max' => 'Mã khu vực kho không được vượt quá :max ký tự.'
            ],
            'description' => [
                'max' => 'Mô tả không được vượt quá :max ký tự.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE.'
            ]
        ];
    }
}