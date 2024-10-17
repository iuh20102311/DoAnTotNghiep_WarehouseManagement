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

class ProductPrice extends Model
{
    use HasFactory;
    protected $table = 'product_prices';
    protected $fillable = ['product_id', 'date_expiry', 'price', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'product_id' => v::notEmpty()->setName('product_id')->setTemplate('Khách hàng không được rỗng'),
            'price' => v::notEmpty()->numericVal()->positive()->setName('price')->setTemplate('Gía tiền không hợp lệ. Gía tiền không được trống và phải là số dương.'),
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