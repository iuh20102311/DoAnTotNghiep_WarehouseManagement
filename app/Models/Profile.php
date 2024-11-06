<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'profiles';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'birthday',
        'avatar',
        'gender',
        'address',
        'ward',
        'district',
        'city',
        'status',
        'created_at',
        'updated_at',
        'deleted'
    ];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdOrders()
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'user_id' => ['required', 'integer'],
            'first_name' => ['required', 'max' => 50],
            'last_name' => ['required', 'max' => 50],
            'phone' => ['required', 'max' => 15],
            'birthday' => ['required', 'date' => 'Y-m-d'],
            'avatar' => ['max' => 255],
            'gender' => ['integer', 'enum' => ['0', '1']],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE']]
        ];

        if ($isUpdate) {
            // Chỉ validate các trường có trong request
            $rules = array_intersect_key($rules, $data);

            // Bỏ qua validate required
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        // Validate foreign key existence
        if (isset($data['user_id']) && !User::where('deleted', false)->find($data['user_id'])) {
            return ['user_id' => ['Người dùng không tồn tại']];
        }

        // Validate unique phone if provided
        if (isset($data['phone'])) {
            $query = self::where('phone', $data['phone'])
                ->where('deleted', false);

            if ($isUpdate) {
                $query->where('id', '!=', $this->id);
            }

            if ($query->exists()) {
                return ['phone' => ['Số điện thoại đã được sử dụng']];
            }
        }

        return null;
    }

    protected function messages()
    {
        return [
            'user_id' => [
                'required' => 'ID người dùng là bắt buộc.',
                'integer' => 'ID người dùng phải là số nguyên.'
            ],
            'first_name' => [
                'required' => 'Tên là bắt buộc.',
                'max' => 'Tên không được vượt quá :max ký tự.'
            ],
            'last_name' => [
                'required' => 'Họ là bắt buộc.',
                'max' => 'Họ không được vượt quá :max ký tự.'
            ],
            'phone' => [
                'required' => 'Số điện thoại là bắt buộc.',
                'max' => 'Số điện thoại không được vượt quá :max ký tự.',
            ],
            'birthday' => [
                'required' => 'Ngày sinh là bắt buộc.',
                'date' => 'Ngày sinh không hợp lệ.'
            ],
            'avatar' => [
                'max' => 'Đường dẫn ảnh đại diện không được vượt quá :max ký tự.'
            ],
            'gender' => [
                'enum' => 'Giới tính phải là 0, 1'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE hoặc INACTIVE.'
            ]
        ];
    }
}