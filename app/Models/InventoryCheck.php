<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCheck extends Model
{
    use HasFactory;

    protected $table = 'inventory_checks';
    protected $fillable = ['storage_area_id', 'check_date', 'status', 'note', 'created_by', 'approved_by', 'approved_at', 'completed_at', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InventoryCheckDetail::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'storage_area_id' => ['required', 'integer'],
            'status' => ['required', 'enum' => ['PENDING', 'APPROVED', 'COMPLETED']],
            'note' => ['max' => 1000],
            'check_date' => ['required', 'date' => 'Y-m-d H:i:s']
        ];

        // Validate cho create/update thông thường
        if ($isUpdate) {
            // Khi update thông thường, bỏ điều kiện required
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        // Validate khi phê duyệt
        if (isset($data['status'])) {
            switch ($data['status']) {
                case 'APPROVED':
                    $rules['approved_by'] = ['required', 'integer'];
                    $rules['approved_at'] = ['required', 'date' => 'Y-m-d H:i:s'];
                    break;
                case 'COMPLETED':
                    $rules['completed_at'] = ['required', 'date' => 'Y-m-d H:i:s'];
                    // Validate thêm actual_quantity trong details khi hoàn thành
                    if (isset($data['details'])) {
                        foreach ($data['details'] as $index => $detail) {
                            if (!isset($detail['actual_quantity']) || $detail['actual_quantity'] < 0) {
                                return ['details.' . $index . '.actual_quantity' => ['Số lượng thực tế không hợp lệ']];
                            }
                        }
                    }
                    break;
                case 'PENDING':
                    // Trường hợp tạo mới, cần người tạo
                    if (!$isUpdate) {
                        $rules['created_by'] = ['required', 'integer'];
                    }
                    break;
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
            'storage_area_id' => [
                'required' => 'Khu vực kho là bắt buộc.',
                'integer' => 'Khu vực kho không hợp lệ.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là PENDING,APPROVED,COMPLETED.'
            ],
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ],
            'created_by' => [
                'required' => 'Người tạo là bắt buộc.',
                'integer' => 'Người tạo không hợp lệ.'
            ],
            'approved_by' => [
                'required' => 'Người duyệt là bắt buộc khi phiếu được duyệt.',
                'integer' => 'Người duyệt không hợp lệ.'
            ],
            'approved_at' => [
                'required' => 'Thời gian duyệt là bắt buộc khi phiếu được duyệt.',
                'date' => 'Thời gian duyệt không hợp lệ.',
                'invalid_date' => 'Thời gian duyệt không phải là định dạng ngày hợp lệ.'
            ],
            'completed_at' => [
                'required' => 'Thời gian hoàn thành là bắt buộc khi phiếu đã hoàn thành.',
                'date' => 'Thời gian hoàn thành không hợp lệ.',
                'invalid_date' => 'Thời gian hoàn thành không phải là định dạng ngày hợp lệ.'
            ],
            'check_date' => [
                'required' => 'Ngày kiểm kê là bắt buộc.',
                'date' => 'Ngày kiểm kê không hợp lệ.',
                'invalid_date' => 'Ngày kiểm kê không phải là định dạng ngày hợp lệ.'
            ]
        ];
    }
}