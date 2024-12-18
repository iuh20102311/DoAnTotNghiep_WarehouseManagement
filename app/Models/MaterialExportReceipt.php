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

class MaterialExportReceipt extends Model
{
    use HasFactory;
    protected $table = 'material_export_receipts';
    protected $fillable = ['code', 'note', 'receipt_date', 'type', 'status', 'image', 'created_at', 'updated_at', 'deleted', 'created_by'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(MaterialExportReceiptDetail::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        // Debug: Log data được gửi vào
        error_log("Validating data: " . json_encode($data));

        $rules = [
            'note' => ['nullable', 'string', 'max' => 500],
            'receipt_date' => ['required', 'date' => 'Y-m-d H:i:s', 'after' => 'now'],
            'type' => ['required', 'enum' => ['RETURN','NORMAL','OTHER','CANCEL']],
            'status' => ['required', 'enum' => ['COMPLETED','TEMPORARY']],
            'created_by' => ['required', 'integer'],
        ];

        if ($isUpdate) {
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            error_log("Validation failed: " . json_encode($validator->getErrors()));
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
            'type' => [
                'required' => 'Loại phiếu xuất là bắt buộc.',
                'enum' => 'Loại phiếu xuất phải là RETURN,NORMAL,OTHER hoặc CANCEL.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là COMPLETED hoặc TEMPORARY.'
            ],
            'created_by' => [
                'required' => 'Người tạo là bắt buộc.',
                'integer' => 'ID người tạo phải là số nguyên.'
            ],
            'receipt_date' => [
                'required' => 'Ngày xuất kho là bắt buộc.',
                'date' => 'Ngày xuất kho không hợp lệ.'
            ]
        ];
    }
}