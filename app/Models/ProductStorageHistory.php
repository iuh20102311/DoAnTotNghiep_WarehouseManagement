<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStorageHistory extends Model
{
    use HasFactory;

    protected $table = 'product_storage_history';
    protected $fillable = ['product_id', 'storage_area_id', 'quantity', 'expiry_date', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function storageArea(): BelongsTo
    {
        return $this->belongsTo(StorageArea::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'product_id' => ['required', 'integer'],
            'storage_area_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min' => 0]
        ];

        if ($isUpdate) {
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
        if (isset($data['product_id']) && !Product::where('deleted', false)->find($data['product_id'])) {
            return ['product_id' => ['Sản phẩm không tồn tại']];
        }

        if (isset($data['storage_area_id']) && !StorageArea::where('deleted', false)->find($data['storage_area_id'])) {
            return ['storage_area_id' => ['Khu vực kho không tồn tại']];
        }

        return null;
    }

    protected function messages()
    {
        return [
            'product_id' => [
                'required' => 'ID sản phẩm là bắt buộc.',
                'integer' => 'ID sản phẩm phải là số nguyên.'
            ],
            'storage_area_id' => [
                'required' => 'ID khu vực kho là bắt buộc.',
                'integer' => 'ID khu vực kho phải là số nguyên.'
            ],
            'quantity' => [
                'required' => 'Số lượng là bắt buộc.',
                'integer' => 'Số lượng phải là số nguyên.',
                'min' => 'Số lượng không được nhỏ hơn :min.'
            ]
        ];
    }
}