<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class StorageArea extends Model
{
    use HasFactory;

    protected $table = 'storage_areas';
    protected $fillable = ['name', 'description', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function productStorageLocations(): HasMany
    {
        return $this->hasMany(ProductStorageLocation::class);
    }

    public function materialStorageLocations(): HasMany
    {
        return $this->hasMany(MaterialStorageLocation::class);
    }

    public function inventoryChecks(): HasMany
    {
        return $this->hasMany(InventoryCheck::class);
    }

    public function inventoryHistory(): HasMany
    {
        return $this->hasMany(InventoryHistory::class);
    }

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'name' => v::notEmpty()->regex('/^([\p{L}\p{M}]+\s*)+$/u')->setName('name')->setTemplate('Tên không hợp lệ. Tên phải viết hoa chữ cái đầu tiên của mỗi từ và chỉ chứa chữ cái.'),
            'address' => v::notEmpty()->setTemplate('Địa chỉ không hợp lệ. Địa chỉ phải chứa chữ, số và ký tự /.'),
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