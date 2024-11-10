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
            'product_id' => ['required', 'array'],
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

        // Validate từng product_id trong mảng
        foreach ($data['product_id'] as $productId) {
            if (!is_int($productId)) {
                return [
                    'product_id' => ['Các ID sản phẩm phải là số nguyên']
                ];
            }
        }

        // Chỉ kiểm tra trùng ngày khi chuyển sang ACTIVE
        if ($checkDateOverlap) {
            // Kiểm tra trùng lặp cho từng product_id
            foreach ($data['product_id'] as $productId) {
                $existingPrice = $this->where('product_id', $productId)
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
                        'date_overlap' => 'Đã tồn tại bảng giá ACTIVE cho sản phẩm ' . $productId . ' trong khoảng thời gian này.'
                    ];
                }
            }
        }

        return null;
    }


    protected function messages()
    {
        return [
            'product_id' => [
                'required' => 'ID sản phẩm là bắt buộc.',
                'integer' => 'ID sản phẩm phải là số nguyên.',
                'exists' => 'Sản phẩm không tồn tại.',
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