<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

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

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'name' => v::notEmpty()->regex('/^([\p{L}\p{M}]+\s*)+$/u')->setName('name')->setTemplate('Tên không hợp lệ. Tên phải viết hoa chữ cái đầu tiên của mỗi từ và chỉ chứa chữ cái.'),
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