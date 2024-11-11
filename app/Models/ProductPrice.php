<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use HasFactory;
    protected $table = 'product_prices';
    protected $fillable = ['product_id', 'date_start', 'date_end', 'price', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function validate(array $data, $isUpdate = false, $checkDateOverlap = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'product_id' => ['required'],
            'date_start' => ['required', 'date' => 'Y-m-d'],
            'date_end' => ['required', 'date' => 'Y-m-d', 'after' => 'date_start'],
            'price' => ['required', 'integer', 'min' => 0],
        ];

        if ($isUpdate) {
            $rules['status'] = ['required', 'enum' => ['ACTIVE', 'INACTIVE']];
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        // Chỉ kiểm tra trùng ngày khi chuyển sang ACTIVE
        if ($checkDateOverlap) {
            $existingPrice = $this->where('product_id', $data['product_id'])
                ->where('status', 'ACTIVE')
                ->where(function ($query) use ($data) {
                    $query->whereBetween('date_start', [$data['date_start'], $data['date_end']])
                        ->orWhereBetween('date_end', [$data['date_start'], $data['date_end']])
                        ->orWhere(function ($q) use ($data) {
                            $q->where('date_start', '<=', $data['date_start'])
                                ->where('date_end', '>=', $data['date_end']);
                        });
                })
                ->where('id', '!=', $this->id ?? 0)
                ->exists();

            if ($existingPrice) {
                return [
                    'date_overlap' => 'Đã tồn tại bảng giá ACTIVE cho sản phẩm ' . $data['product_id'] . ' trong khoảng thời gian này.'
                ];
            }
        }

        return null;
    }


    protected function messages()
    {
        return [
            'product_id' => [
                'required' => 'ID sản phẩm là bắt buộc.',
            ],
            'date_start' => [
                'required' => 'Ngày bắt đầu là bắt buộc.',
                'date' => 'Ngày bắt đầu không hợp lệ.',
            ],
            'date_end' => [
                'required' => 'Ngày kết thúc là bắt buộc.',
                'date' => 'Ngày kết thúc không hợp lệ.',
                'after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            ],
            'price' => [
                'required' => 'Giá là bắt buộc.',
                'integer' => 'Giá phải là số nguyên.',
                'min' => 'Giá không được âm.',
            ],
        ];
    }
}