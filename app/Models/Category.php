<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;
    protected $table = 'categories';
    protected $fillable = ['name', 'type', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories');
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class, 'category_discounts');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'material_categories');
    }

    // Quan hệ bảng nhiều nhiều
    public function categoryDiscounts(): HasMany
    {
        return $this->hasMany(CategoryDiscount::class);
    }

    public function validate(array $data)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'string', 'min' => 2, 'max' => 50, 'no_special_chars', 'no_emoji', 'no_whitespace'],
            'type' => ['required', 'string', 'enum' => ['PRODUCT','MATERIAL','PACKAGING']],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'SUSPENDED']]
        ];

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        return null;
    }

    protected function messages()
    {
        return [
            'name' => [
                'required' => 'Tên là bắt buộc.',
                'min' => 'Tên loại phải có ít nhất :min ký tự.',
                'max' => 'Tên loại không được vượt quá :max ký tự.',
                'no_whitespace' => 'Không nhập khoảng trắng.',
                'no_special_chars' => 'Không nhập các ký tự đặc biệt.',
                'no_emoji' => 'Không được nhập ký tự chứa emoji.',
            ],
            'type' => [
                'required' => 'Loại là bắt buộc.',
                'enum' => 'Loại phải là PRODUCT,MATERIAL,PACKAGING.',
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc SUSPENDED.',
            ],
        ];
    }
}