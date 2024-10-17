<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Product extends Model
{
    use HasFactory;
    protected $table = 'products';
    protected $fillable = ['sku', 'name', 'packing', 'quantity', 'weight', 'image', 'quantity_available', 'minimum_stock_level', 'description', 'status', 'created_at', 'updated_at', 'deleted'];
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

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'sku' => v::notEmpty()->regex('/^[A-Z\d]+$/')->setName('sku')->setTemplate('Mã sản phẩm không hợp lệ. Mã sản phẩm không được để trống và viết hoa tất cả.'),
            'name' => v::notEmpty()->regex('/^([\p{L}\p{M}]+\s*)+$/u')->setName('name')->setTemplate('Tên không hợp lệ. Tên phải viết hoa chữ cái đầu tiên của mỗi từ và chỉ chứa chữ cái.'),
            'packing' => v::notEmpty()->regex('/^(\p{Lu}\p{Ll}+)(?:\s+\p{Lu}\p{Ll}+)*$/u')->setName('packing')->setTemplate('Loại vật chứa không hợp lệ. Loại vật chứa không được để trống và viết hoa chữ cái đầu.'),
            'price' => v::notEmpty()->numericVal()->positive()->setName('price')->setTemplate('Gía cả không hợp lệ. Gía cả không được trống và phải là số dương.'),
            'quantity' => v::notEmpty()->numericVal()->positive()->setName('quantity')->setTemplate('Số lượng không hợp lệ. Số lượng không được trống và phải là số dương.'),
            'weight' => v::notEmpty()->numericVal()->positive()->setName('weight')->setTemplate('Khối lương không hợp lệ. Khối lương không được trống và phải là số dương.'),
            'image' => v::notEmpty()->setName('image')->setTemplate('Hình ảnh không được rỗng'),
            'status' => v::notEmpty()->in(['ACTIVE', 'DELETED'])->setName('status')->setTemplate('Trạng thái không hợp lệ. Trạng thái chỉ có thể là ACTIVE hoặc DELETED.'),
        ];

        $error = "";
        foreach ($validators as $field => $validator) {
            if ($isUpdate && !array_key_exists($field, $data)) {
                continue;
            }

            try {
                $validator->assert(isset($data[$field]) ? $data[$field] : null);
            } catch (ValidationException $exception) {
                $error = $exception->getMessage();
                break;
            }
        }
        return $error;
    }
}