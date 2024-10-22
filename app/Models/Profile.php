<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Profile extends Model
{
    use HasFactory;
    protected $table = 'profiles';
    protected $fillable = ['user_id', 'first_name', 'last_name', 'phone', 'birthday', 'avatar', 'gender', 'status', 'created_at', 'updated_at', 'deleted'];
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

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'user_id' => v::notEmpty()->setName('user_id')->setTemplate('Người dùng không được rỗng'),
            'first_name' => v::notEmpty()->regex('/^(\p{Lu}\p{Ll}+)(?:\s+\p{Lu}\p{Ll}+)*$/u')->setName('first_name')->setTemplate('Tên lót không hợp lệ. Tên lót không được để trống và viết hoa chữ cái đầu.'),
            'last_name' => v::notEmpty()->regex('/^(\p{Lu}\p{Ll}+)(?:\s+\p{Lu}\p{Ll}+)*$/u')->setName('last_name')->setTemplate('Tên không hợp lệ. Tên không được để trống và viết hoa chữ cái đầu.'),
            'phone' => v::digit()->length(10, 10)->startsWith('0')->setName('phone')->setTemplate('Số điện thoại không được rỗng, phải có 10 chữ số, bắt đầu bằng số 0 và chỉ chứa các chữ số.'),
            'gender' => v::notEmpty()->intVal()->between(0, 1)->setName('gender')->setTemplate('Giới tính không được rỗng và chỉ được nhập 0 hoặc 1.'),
            'avatar' => v::notEmpty()->setName('email')->setTemplate('Email không được rỗng'),
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