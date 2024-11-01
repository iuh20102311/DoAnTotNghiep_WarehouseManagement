<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCheckDetail extends Model
{
    use HasFactory;

    protected $table = 'inventory_check_details';
    protected $fillable = ['product_id', 'inventory_check_id', 'material_id', 'exact_quantity', 'actual_quantity', 'defective_quantity', 'error_description', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function inventoryCheck(): BelongsTo
    {
        return $this->belongsTo(InventoryCheck::class, 'inventory_check_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    // Mutator to enforce constraint programmatically
    public function setProductIdAttribute($value)
    {
        if (!is_null($value)) {
            $this->attributes['product_id'] = $value;
            $this->attributes['material_id'] = null; // Force material_id to null
        }
    }

    public function setMaterialIdAttribute($value)
    {
        if (!is_null($value)) {
            $this->attributes['material_id'] = $value;
            $this->attributes['product_id'] = null; // Force product_id to null
        }
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'inventory_check_id' => ['required', 'integer'],
            'product_id' => ['nullable', 'integer', 'xor:material_id'],
            'material_id' => ['nullable', 'integer', 'xor:product_id'],
            'exact_quantity' => ['required', 'integer', 'min' => 0],
            'actual_quantity' => ['required', 'integer', 'min' => 0],
            'defective_quantity' => ['required', 'integer', 'min' => 0],
            'error_description' => ['nullable', 'string', 'max' => 500]
        ];

        if ($isUpdate) {
            // Chỉ validate các trường có trong request
            $rules = array_intersect_key($rules, $data);

            // Bỏ qua validate required
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }

            // Không cho phép update inventory_check_id
            if (isset($data['inventory_check_id'])) {
                return [
                    'inventory_check_id' => ['Không thể thay đổi phiếu kiểm kho']
                ];
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
            'inventory_check_id' => [
                'required' => 'ID kiểm kho là bắt buộc.',
            ],
            'product_id' => [
                'xor' => 'Chỉ được chọn sản phẩm hoặc nguyên liệu.',
            ],
            'material_id' => [
                'xor' => 'Chỉ được chọn sản phẩm hoặc nguyên liệu.',
            ],
            'exact_quantity' => [
                'required' => 'Số lượng chính xác là bắt buộc.',
                'min' => 'Số lượng chính xác không được âm.',
            ],
            'actual_quantity' => [
                'required' => 'Số lượng thực tế là bắt buộc.',
                'min' => 'Số lượng thực tế không được âm.',
            ],
            'defective_quantity' => [
                'required' => 'Số lượng lỗi là bắt buộc.',
                'min' => 'Số lượng lỗi không được âm.',
            ],
            'error_description' => [
                'max' => 'Mô tả lỗi không được vượt quá :max ký tự.',
            ],
        ];
    }
}
