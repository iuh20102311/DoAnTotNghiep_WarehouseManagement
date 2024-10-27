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

class MaterialImportReceipt extends Model
{
    use HasFactory;
    protected $table = 'material_import_receipts';
    protected $fillable = ['provider_id', 'receipt_id', 'type', 'note', 'receipt_date', 'total_price', 'status', 'image', 'created_at', 'updated_at', 'deleted', 'created_by', 'approved_by', 'receiver_id'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(MaterialImportReceiptDetail::class);
    }

    /**
     * @throws Exception
     */
    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'provider_id' => ['required', 'integer'],
            'receipt_id' => ['required', 'string', 'max' => 50],
            'type' => ['required', 'enum' => ['NORMAL', 'RETURN']],
            'note' => ['nullable', 'string', 'max' => 500],
            'receipt_date' => ['required', 'date' => 'Y-m-d', 'after' => 'now'],
            'total_price' => ['required', 'integer', 'min' => 0],
            'status' => ['required', 'enum' => ['PENDING', 'COMPLETED']],
            'created_by' => ['required', 'integer'],
            'approved_by' => ['nullable', 'integer'],
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
            'provider_id' => [
                'required' => 'Nhà cung cấp là bắt buộc.',
                'integer' => 'ID nhà cung cấp phải là số nguyên.'
            ],
            'receipt_id' => [
                'required' => 'Mã phiếu nhập là bắt buộc.',
                'max' => 'Mã phiếu nhập không được vượt quá :max ký tự.'
            ],
            'type' => [
                'required' => 'Loại phiếu nhập là bắt buộc.',
                'enum' => 'Loại phiếu nhập phải là NORMAL hoặc RETURN.'
            ],
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ],
            'receipt_date' => [
                'required' => 'Ngày nhập là bắt buộc.',
                'date' => 'Ngày nhập không hợp lệ.'
            ],
            'total_price' => [
                'required' => 'Tổng tiền là bắt buộc.',
                'integer' => 'Tổng tiền phải là số nguyên.',
                'min' => 'Tổng tiền không được nhỏ hơn :min.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là PENDING, APPROVED, REJECTED hoặc DELETED.'
            ],
            'created_by' => [
                'required' => 'Người tạo là bắt buộc.',
                'integer' => 'ID người tạo phải là số nguyên.'
            ],
            'approved_by' => [
                'integer' => 'ID người duyệt phải là số nguyên.'
            ],
            'receiver_id' => [
                'required' => 'Người nhận là bắt buộc.',
                'integer' => 'ID người nhận phải là số nguyên.'
            ]
        ];
    }
}