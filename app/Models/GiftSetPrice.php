<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftSetPrice extends Model
{
    use HasFactory;

    protected $table = 'gift_set_prices';
    protected $fillable = ['gift_set_id', 'date_expiry', 'price', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function giftSet(): BelongsTo
    {
        return $this->belongsTo(GiftSet::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'date_expiry' => ['required', 'date' => 'Y-m-d H:i:s', 'after' => 'now'],
            'price' => ['required', 'integer', 'min' => 0],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE']]
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
            'date_expiry' => [
                'required' => 'Ngày hết hạn là bắt buộc.',
                'date' => 'Ngày hết hạn không hợp lệ.',
                'datetime' => 'Ngày hết hạn phải đúng định dạng ngày giờ.',
                'after' => 'Ngày hết hạn phải sau ngày :date.',
                'after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày :date.',
                'invalid_date' => 'Ngày hết hạn không phải là định dạng ngày hợp lệ.'
            ],
            'created_at' => [
                'required' => 'Ngày tạo là bắt buộc.',
                'datetime' => 'Ngày tạo phải đúng định dạng ngày giờ.',
                'invalid_date' => 'Ngày tạo không phải là định dạng ngày hợp lệ.'
            ],
            'start_date' => [
                'required' => 'Ngày bắt đầu là bắt buộc.',
                'date' => 'Ngày bắt đầu không hợp lệ.',
                'after' => 'Ngày bắt đầu phải sau ngày :date.',
                'after_or_equal' => 'Ngày bắt đầu phải sau hoặc bằng ngày :date.',
                'invalid_date' => 'Ngày bắt đầu không phải là định dạng ngày hợp lệ.'
            ],
            'end_date' => [
                'required' => 'Ngày kết thúc là bắt buộc.',
                'date' => 'Ngày kết thúc không hợp lệ.',
                'after' => 'Ngày kết thúc phải sau ngày :date.',
                'after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày :date.',
                'invalid_date' => 'Ngày kết thúc không phải là định dạng ngày hợp lệ.'
            ],
            'price' => [
                'required' => 'Giá là bắt buộc.',
                'integer' => 'Giá phải là số nguyên.',
                'min' => 'Giá không được nhỏ hơn :min.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE hoặc INACTIVE.'
            ]
        ];
    }
}