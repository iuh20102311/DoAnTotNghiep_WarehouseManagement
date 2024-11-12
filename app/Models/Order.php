<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;
    protected $table = 'orders';
    protected $fillable = ['code', 'customer_id', 'created_by', 'order_date', 'delivery_date', 'total_price', 'phone', 'address', 'city', 'district', 'ward', 'status', 'payment_status', 'payment_method', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'created_by');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function giftSets(): BelongsToMany
    {
        return $this->belongsToMany(GiftSet::class, 'order_gift_sets')->withPivot('quantity', 'price');
    }

    // Quan hệ bảng nhiều nhiều
    public function orderGiftSets(): HasMany
    {
        return $this->hasMany(OrderGiftSet::class);
    }

    public function validate(array $data)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'customer_id' => ['required', 'integer'],
            'created_by' => ['required', 'integer'],
            'delivery_date' => ['required', 'date' => 'Y-m-d', 'after' => 'order_date'],
            'phone' => ['required', 'string'],
            'address' => ['required', 'string', 'min' => 5, 'max' => 255],
            'city' => ['required', 'string', 'max' => 100],
            'district' => ['required', 'string', 'max' => 100],
            'ward' => ['required', 'string', 'max' => 100],
            'status' => ['required', 'enum' => ['PROCESSED', 'DELIVERED', 'SHIPPING', 'PENDING', 'CANCELLED', 'RETURNED', 'DRAFT']],
            'payment_status' => ['required', 'enum' => ['PAID', 'PENDING']],
            'payment_method' => ['required', 'enum' => ['CASH', 'BANK_TRANSFER']],
        ];

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        return null;
    }

    protected function messages()
    {
        return [
            'customer_id' => [
                'required' => 'ID khách hàng là bắt buộc.',
                'integer' => 'ID khách hàng phải là số nguyên.',
                'exists' => 'Khách hàng không tồn tại.',
            ],
            'created_by' => [
                'required' => 'Người tạo đơn là bắt buộc.',
                'integer' => 'ID người tạo phải là số nguyên.',
                'exists' => 'Người tạo không tồn tại.',
            ],
            'delivery_date' => [
                'required' => 'Ngày giao hàng là bắt buộc.',
                'date' => 'Ngày giao hàng không hợp lệ.',
                'after' => 'Ngày giao hàng phải sau ngày đặt hàng.',
            ],
            'phone' => [
                'required' => 'Số điện thoại là bắt buộc.',
                'string' => 'Số điện thoại phải là chuỗi.',
                'regex' => 'Số điện thoại phải có 10 chữ số.',
            ],
            'address' => [
                'required' => 'Địa chỉ là bắt buộc.',
                'string' => 'Địa chỉ phải là chuỗi.',
                'min' => 'Địa chỉ phải có ít nhất :min ký tự.',
                'max' => 'Địa chỉ không được vượt quá :max ký tự.',
            ],
            'city' => [
                'required' => 'Tỉnh/Thành phố là bắt buộc.',
                'string' => 'Tỉnh/Thành phố phải là chuỗi.',
                'max' => 'Tỉnh/Thành phố không được vượt quá :max ký tự.',
            ],
            'district' => [
                'required' => 'Quận/Huyện là bắt buộc.',
                'string' => 'Quận/Huyện phải là chuỗi.',
                'max' => 'Quận/Huyện không được vượt quá :max ký tự.',
            ],
            'ward' => [
                'required' => 'Phường/Xã là bắt buộc.',
                'string' => 'Phường/Xã phải là chuỗi.',
                'max' => 'Phường/Xã không được vượt quá :max ký tự.',
            ],
            'status' => [
                'required' => 'Trạng thái đơn hàng là bắt buộc.',
                'enum' => 'Trạng thái đơn hàng không hợp lệ, phải là PROCESSED, DELIVERED, SHIPPING, PENDING, CANCELLED, RETURNED, DRAFT.',
            ],
            'payment_status' => [
                'required' => 'Trạng thái thanh toán là bắt buộc.',
                'enum' => 'Trạng thái thanh toán không hợp lệ.',
            ],
            'payment_method' => [
                'required' => 'Phương thức thanh toán là bắt buộc.',
                'enum' => 'Phương thức thanh toán không hợp lệ.',
            ],
        ];
    }
}