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
    protected $fillable = ['sku', 'name', 'packing', 'unit', 'weight', 'origin', 'image', 'quantity_available', 'minimum_stock_level', 'minimum_stock_level', 'description', 'usage_time', 'status', 'created_at', 'updated_at', 'deleted'];
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

    public function storageLocations(): HasMany
    {
        return $this->hasMany(ProductStorageLocation::class);
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
        return $this->hasMany(InventoryHistory::class);
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
            'sku' => ['required', 'string'],
            'name' => ['required', 'string'],
            'packing' => ['required', 'string'],
            'unit' => ['required', 'string'],
            'weight' => ['required', 'numeric', 'min' => 0],
            'origin' => ['required', 'string'],
            'image' => ['required', 'string'],
            'minimum_stock_level' => ['nullable', 'integer', 'min' => 0],
            'usage_time' => ['nullable', 'string'],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'OUT_OF_STOCK']]
        ];

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
            'sku' => [
                'required' => 'Mã sản phẩm là bắt buộc.',
            ],
            'name' => [
                'required' => 'Tên sản phẩm là bắt buộc.',
            ],
            'packing' => [
                'required' => 'Loại vật chứa là bắt buộc.',
            ],
            'unit' => [
                'required' => 'Đơn vị tính là bắt buộc.',
            ],
            'weight' => [
                'required' => 'Khối lượng là bắt buộc.',
                'numeric' => 'Khối lượng phải là số.',
                'min' => 'Khối lượng không được âm.'
            ],
            'origin' => [
                'required' => 'Xuất xứ sản phẩm là bắt buộc.',
            ],
            'image' => [
                'required' => 'Hình ảnh là bắt buộc.'
            ],
            'minimum_stock_level' => [
                'integer' => 'Mức tồn kho tối thiểu phải là số nguyên.',
                'min' => 'Mức tồn kho tối thiểu không được âm.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'in' => 'Trạng thái không hợp lệ. Trạng thái chỉ có thể là ACTIVE hoặc DELETED.'
            ]
        ];
    }
}