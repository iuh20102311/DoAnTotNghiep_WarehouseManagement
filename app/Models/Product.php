<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    protected $table = 'products';
    protected $fillable = ['sku', 'name', 'packing', 'unit', 'weight', 'origin', 'image', 'quantity_available', 'minimum_stock_level', 'maximum_stock_level', 'description', 'usage_time', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class, 'product_discounts');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function storageHistories(): HasMany
    {
        return $this->hasMany(ProductStorageHistory::class);
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function exportReceiptDetails(): HasMany
    {
        return $this->hasMany(ProductExportReceiptDetail::class);
    }

    public function importReceiptDetails(): HasMany
    {
        return $this->hasMany(ProductImportReceiptDetail::class);
    }

    public function giftSets(): BelongsToMany
    {
        return $this->belongsToMany(GiftSet::class, 'gift_set_products')->withPivot('quantity');
    }

    public function inventoryCheckDetails(): HasMany
    {
        return $this->hasMany(InventoryCheckDetail::class);
    }

    public function inventoryHistory(): HasMany
    {
        return $this->hasMany(ProductInventoryHistory::class);
    }

    // Quan hệ bảng nhiều nhiều
    public function productDiscounts(): HasMany
    {
        return $this->hasMany(ProductDiscount::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'string'],
            'packing' => ['required', 'string'],
            'unit' => ['required', 'string'],
            'weight' => ['required', 'numeric', 'min' => 0],
            'origin' => ['required', 'string'],
            'image' => ['required', 'string'],
            'minimum_stock_level' => ['nullable', 'integer', 'min' => 0],
            'maximum_stock_level' => ['nullable', 'integer', 'min' => 0],
            'usage_time' => ['nullable', 'string'],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'OUT_OF_STOCK']],
        ];

        // Thêm validate greater_than/less_than chỉ khi cả 2 field đều được nhập và không rỗng
        if (!empty($data['minimum_stock_level']) && !empty($data['maximum_stock_level'])) {
            $rules['minimum_stock_level'][] = 'less_than:maximum_stock_level';
            $rules['maximum_stock_level'][] = 'greater_than:minimum_stock_level';
        }

        if ($isUpdate) {
            $rules = array_intersect_key($rules, $data);
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
                'required' => 'Tên sản phẩm là bắt buộc.',
                'string' => 'Tên sản phẩm phải là chuỗi.'
            ],
            'packing' => [
                'required' => 'Loại vật chứa là bắt buộc.',
                'string' => 'Loại vật chứa phải là chuỗi.'
            ],
            'unit' => [
                'required' => 'Đơn vị tính là bắt buộc.',
                'string' => 'Đơn vị tính phải là chuỗi.'
            ],
            'weight' => [
                'required' => 'Khối lượng là bắt buộc.',
                'numeric' => 'Khối lượng phải là số.',
                'min' => 'Khối lượng không được âm.'
            ],
            'origin' => [
                'required' => 'Xuất xứ sản phẩm là bắt buộc.',
                'string' => 'Xuất xứ sản phẩm phải là chuỗi.'
            ],
            'image' => [
                'required' => 'Hình ảnh là bắt buộc.',
                'string' => 'Hình ảnh phải là chuỗi.'
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
            'usage_time' => [
                'string' => 'Thời gian sử dụng phải là chuỗi.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái không hợp lệ. Trạng thái chỉ có thể là ACTIVE, INACTIVE hoặc OUT_OF_STOCK.'
            ]
        ];
    }
}