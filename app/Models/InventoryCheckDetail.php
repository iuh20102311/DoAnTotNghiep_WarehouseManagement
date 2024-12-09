<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCheckDetail extends Model
{
    use HasFactory;

    protected $table = 'inventory_check_details';
    protected $fillable = [
        'product_history_id',
        'inventory_check_id',
        'material_history_id',
        'system_quantity',
        'actual_quantity',
        'reason',
        'created_at',
        'updated_at',
        'deleted'
    ];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function inventoryCheck(): BelongsTo
    {
        return $this->belongsTo(InventoryCheck::class, 'inventory_check_id');
    }

    public function productHistory(): BelongsTo
    {
        return $this->belongsTo(ProductStorageHistory::class, 'product_history_id');
    }

    public function materialHistory(): BelongsTo
    {
        return $this->belongsTo(MaterialStorageHistory::class, 'material_history_id');
    }

    // Mutator to enforce constraint programmatically
    public function setProductHistoryIdAttribute($value)
    {
        if (!is_null($value)) {
            $this->attributes['product_history_id'] = $value;
            $this->attributes['material_history_id'] = null; // Force material_history_id to null
        }
    }

    public function setMaterialHistoryIdAttribute($value)
    {
        if (!is_null($value)) {
            $this->attributes['material_history_id'] = $value;
            $this->attributes['product_history_id'] = null; // Force product_history_id to null
        }
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'inventory_check_id' => ['required', 'integer'],
            'product_history_id' => ['nullable', 'integer', 'xor:material_history_id'],
            'material_history_id' => ['nullable', 'integer', 'xor:product_history_id'],
            'system_quantity' => ['required', 'integer', 'min' => 0],
            'actual_quantity' => ['required', 'integer', 'min' => 0],
            'reason' => ['nullable', 'string', 'max' => 500]
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
            'product_history_id' => [
                'xor' => 'Chỉ được chọn lịch sử sản phẩm hoặc lịch sử nguyên liệu.',
            ],
            'material_history_id' => [
                'xor' => 'Chỉ được chọn lịch sử sản phẩm hoặc lịch sử nguyên liệu.',
            ],
            'system_quantity' => [
                'required' => 'Số lượng hệ thống là bắt buộc.',
                'min' => 'Số lượng hệ thống không được âm.',
            ],
            'actual_quantity' => [
                'required' => 'Số lượng thực tế là bắt buộc.',
                'min' => 'Số lượng thực tế không được âm.',
            ],
            'reason' => [
                'max' => 'Lý do không được vượt quá :max ký tự.',
            ],
        ];
    }
}