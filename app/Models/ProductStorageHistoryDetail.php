<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStorageHistoryDetail extends Model
{
    protected $table = 'product_storage_history_details';
    protected $fillable = [
        'product_storage_history_id',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'action_type',
        'created_at',
        'created_by'
    ];
    public $timestamps = false;

    public function productStorageHistory(): BelongsTo
    {
        return $this->belongsTo(ProductStorageHistory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validate(array $data)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'product_storage_history_id' => ['required', 'integer'],
            'quantity_before' => ['required', 'numeric', 'min' => 0],
            'quantity_change' => ['required', 'numeric'],
            'quantity_after' => ['required', 'numeric', 'min' => 0],
            'action_type' => ['required', 'enum' => ['EXPORT_NORMAL', 'EXPORT_CANCEL', 'IMPORT_NORMAL', 'IMPORT_RETURN', 'CHECK']],
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
            'product_storage_history_id' => [
                'required' => 'ID lịch sử lưu trữ sản phẩm là bắt buộc.',
                'integer' => 'ID lịch sử lưu trữ sản phẩm phải là số nguyên.',
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