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
        return $this->belongsTo(Product::class);
    }

    public function validate(array $data)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'product_id' => ['required', 'integer'],
            'date_start' => ['required', 'date' => 'Y-m-d'],
            'date_end' => ['required', 'date' => 'Y-m-d', 'after' => 'date_start'],
            'price' => ['required', 'integer', 'min' => 0],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE']],
        ];

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        // Kiểm tra xem có khoảng thời gian nào bị chồng chéo không
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
            return ['date_overlap' => 'Đã tồn tại bảng giá cho sản phẩm trong khoảng thời gian này.'];
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
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE hoặc INACTIVE.',
            ],
        ];
    }
}