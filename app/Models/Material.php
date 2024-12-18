<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $table = 'materials';
    protected $fillable = ['name', 'sku', 'unit', 'weight', 'origin', 'packing', 'quantity_available' , 'image' ,'minimum_stock_level', 'maximum_stock_level', 'status', 'created_at', 'updated_at', 'note', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'material_categories');
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_materials');
    }

    public function storageHistories(): HasMany
    {
        return $this->hasMany(MaterialStorageHistory::class);
    }

    public function exportReceiptDetails(): HasMany
    {
        return $this->hasMany(MaterialExportReceiptDetail::class);
    }

    public function importReceiptDetails(): HasMany
    {
        return $this->hasMany(MaterialImportReceiptDetail::class);
    }

    public function inventoryHistory(): HasMany
    {
        return $this->hasMany(MaterialStorageHistoryDetail::class);
    }

    // Quan hệ bảng nhiều nhiều
    public function materialCategories(): HasMany
    {
        return $this->hasMany(MaterialCategory::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());
        $rules = [
            'name' => ['required', 'max' => 255],
            'unit' => ['required', 'max' => 50],
            'weight' => ['required', 'numeric', 'min' => 0],
            'origin' => ['required', 'max' => 255],
            'packing' => ['required', 'max' => 255],
            'minimum_stock_level' => ['nullable', 'integer', 'min' => 0],
            'maximum_stock_level' => ['nullable', 'integer', 'min' => 0],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'OUT_OF_STOCK']],
            'note' => ['max' => 255]
        ];

        // Thêm validate greater_than/less_than chỉ khi cả 2 field đều được nhập
        if (!empty($data['minimum_stock_level']) && !empty($data['maximum_stock_level'])) {
            $rules['minimum_stock_level'][] = 'less_than:maximum_stock_level';
            $rules['maximum_stock_level'][] = 'greater_than:minimum_stock_level';
        }

        if ($isUpdate) {
            // Chỉ validate các trường có trong request
            $rules = array_intersect_key($rules, $data);
            // Bỏ qua validate required
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }
        return null;
    }

    protected function messages()
    {
        return [
            'name' => [
                'required' => 'Tên vật liệu là bắt buộc.',
                'max' => 'Tên vật liệu không được vượt quá :max ký tự.'
            ],
            'unit' => [
                'required' => 'Đơn vị tính là bắt buộc.',
                'max' => 'Đơn vị tính không được vượt quá :max ký tự.'
            ],
            'weight' => [
                'required' => 'Khối lượng là bắt buộc.',
                'numeric' => 'Khối lượng phải là số.',
                'min' => 'Khối lượng không được nhỏ hơn :min.'
            ],
            'origin' => [
                'required' => 'Xuất xứ là bắt buộc.',
                'max' => 'Xuất xứ không được vượt quá :max ký tự.'
            ],
            'packing' => [
                'required' => 'Loại chứa là bắt buộc.',
                'max' => 'Loại chứa không được vượt quá :max ký tự.'
            ],
            'minimum_stock_level' => [
                'integer' => 'Mức tồn kho tối thiểu phải là số nguyên.',
                'min' => 'Mức tồn kho tối thiểu không được âm.',
                'less_than' => 'Mức tồn kho tối thiểu phải nhỏ hơn mức tồn kho tối đa.'
            ],
            'maximum_stock_level' => [
                'integer' => 'Mức tồn kho tối đa phải là số nguyên.',
                'min' => 'Mức tồn kho tối đa không được âm.',
                'greater_than' => 'Mức tồn kho tối đa phải lớn hơn mức tồn kho tối thiểu.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc OUT_OF_STOCK.'
            ],
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ]
        ];
    }
}