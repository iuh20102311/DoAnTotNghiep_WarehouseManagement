<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Discount extends Model
{
    use HasFactory;
    protected $table = 'discounts';
    protected $fillable = ['coupon_code', 'discount_value', 'discount_unit', 'minimum_order_value', 'maximum_discount_value', 'valid_until', 'valid_start', 'status', 'note', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_discounts');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_discounts');
    }

    // Quan hệ bảng nhiều nhiều
    public function categoryDiscounts(): HasMany
    {
        return $this->hasMany(CategoryDiscount::class);
    }

    public function productDiscounts(): HasMany
    {
        return $this->hasMany(ProductDiscount::class);
    }

    public function validate(array $data, bool $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'coupon_code' => ($isUpdate ? ['required' , 'string', 'min' => 2, 'max' => 15, 'no_special_chars', 'no_emoji', 'no_whitespace'] : ['required' , 'string', 'min' => 2, 'max' => 15, 'no_special_chars', 'no_emoji', 'no_whitespace', 'unique' => [Discount::class, 'coupon_code']]),
            'discount_value' => ['required', 'integer', 'no_special_chars', 'no_emoji', 'no_whitespace'],
            'discount_unit' => ['required', 'string', 'no_emoji', 'no_whitespace'],
            'minimum_order_value' => ['required', 'integer', 'no_special_chars', 'no_emoji', 'no_whitespace'],
            'maximum_discount_value' => ['required', 'integer', 'no_special_chars', 'no_emoji', 'no_whitespace'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_start'],
            'valid_start' => ['required', 'date', 'before_or_equal:valid_until']
        ];

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        return null;
    }

    protected function messages()
    {
        return [
            'coupon_code' => [
                'required' => 'Coupon code là bắt buộc.',
                'min' => 'Coupon code phải có ít nhất :min ký tự.',
                'max' => 'Coupon code không được vượt quá :max ký tự.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
                'unique' => 'Coupon code này đã được sử dụng.',
            ],
            'discount_value' => [
                'required' => 'Giá trị giảm giá là bắt buộc.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'discount_unit' => [
                'required' => 'Đơn vị giảm giá là bắt buộc.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'minimum_order_value' => [
                'required' => 'Gía trị đơn hàng thấp nhất là bắt buộc.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'maximum_discount_value' => [
                'required' => 'Gía trị giảm cao nhất là bắt buộc.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'valid_until' => [
                'required' => 'Ngày hết hạn là bắt buộc.',
                'date' => 'Ngày hết hạn không hợp lệ.',
                'after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày bắt đầu.'
            ],
            'valid_start' => [
                'required' => 'Ngày bắt đầu là bắt buộc.',
                'date' => 'Ngày bắt đầu không hợp lệ.',
                'before_or_equal' => 'Ngày bắt đầu phải trước hoặc bằng ngày hết hạn.'
            ]
        ];
    }
}