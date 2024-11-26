<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialInventoryHistory extends Model
{
    use HasFactory;

    protected $table = 'material_inventory_history';
    protected $fillable = [
        'storage_area_id',
        'material_id',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'remaining_quantity',
        'action_type',
        'created_at',
        'created_by'
    ];
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validate(array $data)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'storage_area_id' => ['required', 'integer'],
            'material_id' => ['required', 'integer'],
            'quantity_before' => ['required', 'numeric', 'min' => 0],
            'quantity_change' => ['required', 'numeric'],
            'quantity_after' => ['required', 'numeric', 'min' => 0],
            'remaining_quantity' => ['required', 'numeric', 'min' => 0],
            'action_type' => ['required', 'enum' => ['EXPORT_NORMAL', 'EXPORT_RETURN', 'EXPORT_CANCEL', 'IMPORT_NORMAL', 'IMPORT_RETURN', 'CHECK']],
            'created_by' => ['required', 'integer'],
        ];

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        return null;
    }

    protected function messages()
    {
        return [
            'storage_area_id' => [
                'required' => 'ID khu vực lưu trữ là bắt buộc.',
                'integer' => 'ID khu vực lưu trữ phải là số nguyên.',
            ],
            'material_id' => [
                'required' => 'ID nguyên liệu là bắt buộc.',
                'integer' => 'ID nguyên liệu phải là số nguyên.',
            ],
            'quantity_before' => [
                'required' => 'Số lượng trước khi thay đổi là bắt buộc.',
                'numeric' => 'Số lượng trước khi thay đổi phải là số.',
                'min' => 'Số lượng trước khi thay đổi không được âm.',
            ],
            'quantity_change' => [
                'required' => 'Số lượng thay đổi là bắt buộc.',
                'numeric' => 'Số lượng thay đổi phải là số.',
            ],
            'quantity_after' => [
                'required' => 'Số lượng sau khi thay đổi là bắt buộc.',
                'numeric' => 'Số lượng sau khi thay đổi phải là số.',
                'min' => 'Số lượng sau khi thay đổi không được âm.',
            ],
            'remaining_quantity' => [
                'required' => 'Số lượng còn lại là bắt buộc.',
                'numeric' => 'Số lượng còn lại phải là số.',
                'min' => 'Số lượng còn lại không được âm.',
            ],
            'action_type' => [
                'required' => 'Loại hành động là bắt buộc.',
                'enum' => 'Loại hành động không hợp lệ.',
            ],
            'created_by' => [
                'required' => 'Người tạo là bắt buộc.',
                'integer' => 'ID người tạo phải là số nguyên.',
            ],
        ];
    }
}
