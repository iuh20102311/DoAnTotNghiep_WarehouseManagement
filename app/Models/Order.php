<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;


class Order extends Model
{
    use HasFactory;
    protected $table = 'orders';
    protected $fillable = ['customer_id', 'created_by', 'total_price', 'phone', 'address', 'city', 'district', 'ward', 'status', 'created_at', 'updated_at', 'deleted'];
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

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'customer_id' => v::notEmpty()->setName('group_customer_id')->setTemplate('Khách hàng không được rỗng'),
            'created_by' => v::notEmpty()->setName('create_by')->setTemplate('Người tạo đơn hàng không được rỗng'),
            'total_price' => v::notEmpty()->numericVal()->positive()->setName('total_price')->setTemplate('Tổng tiền không hợp lệ. Tổng tiền không được trống và phải là số dương.'),
            'phone' => v::digit()->length(10, 10)->startsWith('0')->setName('phone')->setTemplate('Số điện thoại không được rỗng, phải có 10 chữ số, bắt đầu bằng số 0 và chỉ chứa các chữ số.'),
            'address' => v::notEmpty()->setTemplate('Địa chỉ không được trống'),
//            'city' => v::notEmpty()->regex('/^([\p{L}\p{M}]+\s*)+$/u')->setName('city')->setTemplate('Thành phố không hợp lệ. Thành phố phải viết hoa chữ cái đầu tiên của mỗi từ và chỉ chứa chữ cái.'),
//            'district' => v::notEmpty()->regex('/^([\p{L}\p{M}]+\s*)+$/u')->setName('district')->setTemplate('Tên quận/huyện không hợp lệ. Tên quận/huyện phải viết hoa chữ cái đầu tiên của mỗi từ và chỉ chứa chữ cái.'),
//            'ward' => v::notEmpty()->regex('/^(?=.*[\p{L}\p{M}])(?=.*\d)([\p{L}\p{M}]+\s*)*?([\p{L}\p{M}]*\d+)?$/u')->setName('ward')->setTemplate('Phường không hợp lệ. Phường phải chứa chữ và số, và mỗi từ phải viết hoa chữ cái đầu tiên.'),
            'status' => v::notEmpty()->in(['ACTIVE', 'DELETED'])->setName('status')->setTemplate('Trạng thái không hợp lệ. Trạng thái chỉ có thể là ACTIVE hoặc DELETED.'),
        ];

        $error = "";
        foreach ($validators as $field => $validator) {
            if ($isUpdate && !array_key_exists($field, $data)) {
                continue;
            }

            try {
                $validator->assert(isset($data[$field]) ? $data[$field] : null);
            } catch (ValidationException $exception) {
                $error = $exception->getMessage();
                break;
            }
        }
        return $error;
    }
}