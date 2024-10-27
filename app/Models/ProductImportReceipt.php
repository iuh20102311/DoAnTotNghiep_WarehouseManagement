<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImportReceipt extends Model
{
    use HasFactory;
    protected $table = 'product_import_receipts';
    protected $fillable = ['type', 'receipt_date', 'note', 'status', 'image', 'created_at', 'updated_at', 'deleted', 'created_by', 'receiver_id'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProductImportReceiptDetail::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'type' => ['required', 'enum' => ['NORMAL', 'RETURN']],
            'receipt_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max' => 500],
            'status' => ['required', 'enum' => ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED']],
            'created_by' => ['required', 'integer'],
            'receiver_id' => ['required', 'integer']
        ];

        if ($isUpdate) {
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
            'type' => [
                'required' => 'Loại phiếu nhập là bắt buộc.',
                'enum' => 'Loại phiếu nhập phải là NORMAL hoặc RETURN.'
            ],
            'receipt_date' => [
                'required' => 'Ngày nhập là bắt buộc.',
                'date' => 'Ngày nhập không hợp lệ.'
            ],
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là PENDING, APPROVED, REJECTED hoặc COMPLETED.'
            ],
            'created_by' => [
                'required' => 'Người tạo là bắt buộc.',
                'integer' => 'ID người tạo phải là số nguyên.'
            ],
            'receiver_id' => [
                'required' => 'Người nhận là bắt buộc.',
                'integer' => 'ID người nhận phải là số nguyên.'
            ]
        ];
    }
}