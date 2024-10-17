<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class MaterialInventory extends Model
{
    use HasFactory;
    protected $table = 'material_inventories';
    protected $fillable = ['provider_id','material_id','warehouse_id','quantity_available','minimum_stock_level','status','created_at','updated_at','deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;


    public function provider(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'provider_id' => v::notEmpty()->setName('product_id')->setTemplate('Nhà cung cấp không được rỗng'),
            'material_id' => v::notEmpty()->setName('product_id')->setTemplate('Nguyên liệu không được rỗng'),
            'warehouse_id' => v::notEmpty()->setName('warehouse_id')->setTemplate('Nhà kho không được rỗng'),
            'status' => v::notEmpty()->in(['ACTIVE', 'DELETED'])->setName('status')->setTemplate('Trạng thái không hợp lệ. Trạng thái chỉ có thể là ACTIVE hoặc DELETED.'),
            'quantity_available' => v::notEmpty()->numericVal()->positive()->setName('quantity_available')->setTemplate('Số lượng hiện có không hợp lệ. Số lượng hiện có không được trống và phải là số dương.'),
            'minimum_stock_level' => v::notEmpty()->numericVal()->positive()->setName('minimum_stock_level')->setTemplate('Mức tồn kho tối thiểu không hợp lệ. Mức tồn kho tối thiểu không được trống và phải là số dương.'),
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