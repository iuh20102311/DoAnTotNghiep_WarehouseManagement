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

class MaterialExportReceipt extends Model
{
    use HasFactory;
    protected $table = 'material_export_receipts';
    protected $fillable = ['note', 'receipt_date', 'type', 'status', 'created_at', 'updated_at', 'deleted', 'created_by', 'approved_by', 'receiver_id'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(MaterialExportReceiptDetail::class);
    }

    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false) : string
    {
        $validators = [
            'warehouse_id' => v::notEmpty()->setName('warehouse_id')->setTemplate('Nhà kho không được rỗng'),
            'type' => v::in(['NORMAL', 'RETURN'])->setName('type')->setTemplate('Loại không hợp lệ. Loại chỉ có thể là PRODUCT hoặc MATERIAL.'),
            'status' => v::in(['ACTIVE', 'DELETED'])->setName('status')->setTemplate('Trạng thái không hợp lệ. Trạng thái chỉ có thể là ACTIVE hoặc DELETED.'),
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