<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Provider extends Model
{
    use HasFactory;
    protected $table = 'providers';
    protected $fillable = ['name', 'address', 'city', 'district', 'ward', 'phone', 'email', 'note', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'provider_materials');
    }

    public function materialImportReceipts(): HasMany
    {
        return $this->hasMany(MaterialImportReceipt::class);
    }

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'name' => v::notEmpty()->regex('/^([\p{L}\p{M}]+\s*)+$/u')->setName('name')->setTemplate('Tên không hợp lệ. Tên phải viết hoa chữ cái đầu tiên của mỗi từ và chỉ chứa chữ cái.'),
            'phone' => v::digit()->length(10, 10)->startsWith('0')->setName('phone')->setTemplate('Số điện thoại không được rỗng, phải có 10 chữ số, bắt đầu bằng số 0 và chỉ chứa các chữ số.'),
            'email' => v::notEmpty()->email()->endsWith('@gmail.com')->setName('email')->setTemplate('Email không được rỗng, phải hợp lệ và phải có phần cuối là @gmail.com'),
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