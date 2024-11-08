<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftSet extends Model
{
    use HasFactory;

    protected $table = 'gift_sets';
    protected $fillable = ['name', 'description', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'gift_set_products')->withPivot('quantity');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(GiftSetPrice::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_gift_sets')->withPivot('quantity', 'price');
    }

    // Quan hệ bảng nhiều nhiều
    public function giftSetProducts(): HasMany
    {
        return $this->hasMany(GiftSetProduct::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'string', 'min' => 2, 'max' => 100, 'no_special_chars', 'no_emoji'],
            'description' => ['nullable', 'string', 'max' => 500],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'OUT_OF_STOCK']]
        ];

        // Nếu là update thì không bắt buộc phải có các trường
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
            'name' => [
                'required' => 'Tên quà tặng là bắt buộc.',
                'min' => 'Tên quà tặng phải có ít nhất :min ký tự.',
                'max' => 'Tên quà tặng không được vượt quá :max ký tự.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'description' => [
                'max' => 'Mô tả không được vượt quá :max ký tự.',
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc OUT_OF_STOCK.',
            ],
        ];
    }
}