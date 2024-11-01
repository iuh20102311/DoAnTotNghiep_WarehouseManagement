<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCheck extends Model
{
    use HasFactory;

    protected $table = 'inventory_checks';
    protected $fillable = ['storage_area_id', 'check_date', 'status', 'note', 'created_by', 'created_at', 'updated_at', 'deleted'];
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

    public function details(): HasMany
    {
        return $this->hasMany(InventoryCheckDetail::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'storage_area_id' => ['required', 'integer'],
            'check_date' => ['required', 'date' => 'Y-m-d H:i:s'],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE']],
            'note' => ['max' => 1000],
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
            'storage_area_id' => [
                'required' => 'Khu vực kho là bắt buộc.',
                'integer' => 'Khu vực kho không hợp lệ.',
            ],
            'check_date' => [
                'required' => 'Ngày kiểm kê là bắt buộc.',
                'date' => 'Ngày kiểm kê không hợp lệ.',
                'invalid_date' => 'Ngày kiểm kê không phải là định dạng ngày hợp lệ.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là DRAFT, COMPLETED hoặc CANCELLED.'
            ],
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ],
            'created_by' => [
                'required' => 'Người tạo là bắt buộc.',
                'integer' => 'Người tạo không hợp lệ.',
            ]
        ];
    }
}