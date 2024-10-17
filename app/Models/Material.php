<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Material extends Model
{
    use HasFactory;

    protected $table = 'materials';
    protected $fillable = ['name', 'unit', 'weight', 'origin', 'quantity_available', 'quantity' ,'minimum_stock_level', 'status', 'created_at', 'updated_at', 'note', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'material_categories');
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_materials');
    }

    public function storageLocations(): HasMany
    {
        return $this->hasMany(MaterialStorageLocation::class);
    }

    public function MaterialExportReceiptDetails(): HasMany
    {
        return $this->hasMany(MaterialExportReceiptDetail::class, 'material_id');
    }

    public function MaterialImportReceiptDetails(): HasMany
    {
        return $this->hasMany(MaterialImportReceiptDetail::class, 'material_id');
    }

    public function MaterialInventories()
    {
        return $this->hasMany(MaterialInventory::class,'material_id');
    }

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'name' => v::notEmpty()->regex('/^([\p{L}\p{M}]+\s*)+$/u')->setName('name')->setTemplate('Tên không hợp lệ. Tên phải viết hoa chữ cái đầu tiên của mỗi từ và chỉ chứa chữ cái.'),
            'unit' => v::notEmpty()->regex('/^\p{Lu}\p{Ll}*$/u')->setName('unit')->setTemplate('Đơn vị không hợp lệ. Đơn vị không được để trống và viết hoa chữ cái đầu'),
            'origin' => v::notEmpty()->regex('/^(\p{Lu}\p{Ll}+)(?:\s+\p{Lu}\p{Ll}+)*$/u')->setName('origin')->setTemplate('Xuất xứ không hợp lệ. Xuất xứ không được để trống và viết hoa chữ cái đầu.'),
            'status' => v::notEmpty()->in(['IN_STOCK','OUT_OF_STOCK','TEMPORARILY_SUSP', 'DELETED'])->setName('status')->setTemplate('Trạng thái không hợp lệ. Trạng thái chỉ có thể là ACTIVE hoặc DELETED.'),
            'weight' => v::notEmpty()->numericVal()->positive()->setName('weight')->setTemplate('Khối lượng không hợp lệ. Khối lượng không được để trống và phải là số không âm.'),
            'quantity' => v::notEmpty()->numericVal()->positive()->setName('quantity')->setTemplate('Số lượng không hợp lệ. Số lượng không được trống và phải là số dương.'),
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