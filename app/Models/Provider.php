<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;
    protected $table = 'providers';
    protected $fillable = [
        'code',
        'name',
        'website',
        'address',
        'city',
        'district',
        'ward',
        'representative_name',
        'representative_phone',
        'representative_email',
        'phone',
        'email',
        'note',
        'status',
        'created_at',
        'updated_at',
        'deleted'
    ];
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

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'unique' => [Provider::class, 'name'], 'max' => 255],
            'website' => ['nullable', 'url'],
            'address' => ['required', 'max' => 255],
            'city' => ['required', 'max' => 100],
            'district' => ['required', 'max' => 100],
            'ward' => ['required', 'max' => 100],
            'representative_name' => ['required', 'max' => 255],
            'representative_phone' => ['required', 'max' => 15, 'unique' => [Provider::class, 'representative_phone']],
            'representative_email' => ['required', 'max' => 255, 'email', 'unique' => [Provider::class, 'representative_email']],
            'phone' => ['required', 'max' => 15, 'unique' => [Provider::class, 'phone']],
            'email' => ['required', 'max' => 255, 'email', 'unique' => [Provider::class, 'email']],
            'note' => ['max' => 1000],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'DELETED']]
        ];

        if ($isUpdate) {
            // Chỉ validate các trường có trong request
            $rules = array_intersect_key($rules, $data);

            // Bỏ qua validate required
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        return null;
    }

    protected function messages()
    {
        return [
            'name' => [
                'required' => 'Tên nhà cung cấp là bắt buộc.',
                'max' => 'Tên nhà cung cấp không được vượt quá :max ký tự.',
                'unique' => 'Tên nhà cung cấp này đã được sử dụng.',
            ],
            'website' => [
                'max' => 'Website không được vượt quá :max ký tự.',
                'url' => 'Website không hợp lệ.'
            ],
            'address' => [
                'required' => 'Địa chỉ là bắt buộc.',
                'max' => 'Địa chỉ không được vượt quá :max ký tự.'
            ],
            'city' => [
                'required' => 'Thành phố là bắt buộc.',
                'max' => 'Thành phố không được vượt quá :max ký tự.'
            ],
            'district' => [
                'required' => 'Quận/Huyện là bắt buộc.',
                'max' => 'Quận/Huyện không được vượt quá :max ký tự.'
            ],
            'ward' => [
                'required' => 'Phường/Xã là bắt buộc.',
                'max' => 'Phường/Xã không được vượt quá :max ký tự.'
            ],
            'representative_name' => [
                'required' => 'Tên người đại diện là bắt buộc.',
                'max' => 'Tên người đại diện không được vượt quá :max ký tự.'
            ],
            'representative_phone' => [
                'required' => 'Số điện thoại người đại diện là bắt buộc.',
                'max' => 'Số điện thoại người đại diện không được vượt quá :max ký tự.',
                'unique' => 'Số điện thoại người đại diện này đã được sử dụng.',
            ],
            'representative_email' => [
                'required' => 'Email người đại diện là bắt buộc.',
                'max' => 'Email người đại diện không được vượt quá :max ký tự.',
                'email' => 'Email người đại diện không hợp lệ.',
                'unique' => 'Email người đại diện này đã được sử dụng.',
            ],
            'phone' => [
                'required' => 'Số điện thoại công ty là bắt buộc.',
                'max' => 'Số điện thoại công ty không được vượt quá :max ký tự.',
                'unique' => 'Số điện thoại này đã được sử dụng.',
            ],
            'email' => [
                'required' => 'Email công ty là bắt buộc.',
                'max' => 'Email công ty không được vượt quá :max ký tự.',
                'email' => 'Email công ty không hợp lệ.',
                'unique' => 'Email này đã được sử dụng.',
            ],
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc DELETED.'
            ]
        ];
    }
}