<?php

namespace App\Models;


use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;
use PDO;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';
    protected $fillable = ['group_customer_id', 'name', 'phone', 'gender', 'birthday', 'email', 'address', 'city', 'district', 'ward', 'note', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function groupCustomer(): BelongsTo
    {
        return $this->belongsTo(GroupCustomer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function validate(array $data, bool $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'group_customer_id' => ['required'],
            'name' => ['required', 'string', 'min' => 2, 'max' => 255, 'no_special_chars', 'no_emoji', 'no_whitespace'],
            'phone' => ($isUpdate ? ['required', 'string', 'min' => 10, 'max' => 15, 'no_special_chars', 'no_emoji', 'no_whitespace'] : ['required', 'string', 'min' => 10, 'max' => 15, 'unique' => [Customer::class, 'phone'], 'no_special_chars', 'no_emoji', 'no_whitespace']),
            'gender' => ['required', 'enum' => [0, 1]],
            'email' => ($isUpdate ? ['required', 'email', 'no_emoji', 'no_whitespace'] : ['required', 'email', 'unique' => [Customer::class, 'email'], 'no_emoji', 'no_whitespace']),
            'address' => ['required', 'string', 'max' => 500, 'no_special_chars', 'no_emoji', 'no_whitespace'],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'SUSPENDED']]
        ];

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        return null;
    }

    protected function messages()
    {
        return [
            'group_customer_id' => [
                'required' => 'Nhóm khách hàng là bắt buộc.',
            ],
            'name' => [
                'required' => 'Tên khách hàng là bắt buộc.',
                'min' => 'Tên khách hàng phải có ít nhất :min ký tự.',
                'max' => 'Tên khách hàng không được vượt quá :max ký tự.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'phone' => [
                'required' => 'Số điện thoại là bắt buộc.',
                'min' => 'Số điện thoại phải có ít nhất :min số.',
                'max' => 'Số điện thoại không được vượt quá :max số.',
                'unique' => 'Số điện thoại này đã được sử dụng.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'gender' => [
                'required' => 'Giới tính là bắt buộc.',
                'enum' => 'Giới tính phải là 0 (Nữ) hoặc 1 (Nam).',
            ],
            'email' => [
                'required' => 'Email là bắt buộc.',
                'email' => 'Email không hợp lệ.',
                'unique' => 'Email này đã được sử dụng.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'address' => [
                'required' => 'Địa chỉ là bắt buộc.',
                'max' => 'Địa chỉ không được vượt quá :max ký tự.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc SUSPENDED.',
            ],
        ];
    }
}