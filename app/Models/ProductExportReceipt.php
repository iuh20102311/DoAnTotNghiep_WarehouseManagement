<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class ProductExportReceipt extends Model
{
    use HasFactory;

    protected $table = 'product_export_receipts';
    protected $fillable = ['code', 'order_code', 'note', 'receipt_date', 'type', 'status', 'image', 'created_at', 'updated_at', 'deleted', 'created_by'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProductExportReceiptDetail::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'note' => ['nullable', 'string', 'max' => 500],
            'receipt_date' => ['required', 'date' => 'Y-m-d H:i:s', 'after' => 'now'],
            'type' => ['required', 'enum' => ['NORMAL', 'RETURN']],
            'created_by' => ['required', 'integer'],
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
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ],
            'receipt_date' => [
                'required' => 'Ngày xuất kho là bắt buộc.',
                'date' => 'Ngày xuất kho không hợp lệ.'
            ],
            'type' => [
                'required' => 'Loại phiếu xuất là bắt buộc.',
                'enum' => 'Loại phiếu xuất phải là NORMAL hoặc RETURN.'
            ],
            'created_by' => [
                'required' => 'Người tạo là bắt buộc.',
                'integer' => 'ID người tạo phải là số nguyên.'
            ],
        ];
    }
}