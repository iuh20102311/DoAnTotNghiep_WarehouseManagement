<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupCustomer extends Model
{
    use HasFactory;
    protected $table = 'group_customers';
    protected $fillable = ['name', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class,'group_customer_id');
    }

    public function validate(array $data)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'string', 'min' => 2, 'max' => 50, 'no_special_chars', 'no_emoji', 'no_whitespace'],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE']],
        ];

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        return null;
    }

    protected function messages()
    {
        return [
            'name' => [
                'required' => 'Tên nhóm khách hàng là bắt buộc.',
                'min' => 'Tên nhóm phải có ít nhất :min ký tự.',
                'max' => 'Tên nhóm không được vượt quá :max ký tự.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE hoặc INACTIVE.',
            ],
        ];
    }
}